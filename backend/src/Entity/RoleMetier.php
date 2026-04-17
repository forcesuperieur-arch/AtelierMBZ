<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'roles_metier')]
#[ORM\UniqueConstraint(columns: ['atelier_id', 'code'])]
#[ApiResource(
    shortName: 'RoleMetier',
    normalizationContext: ['groups' => ['role_metier:read']],
    denormalizationContext: ['groups' => ['role_metier:write']],
    operations: [
        new GetCollection(uriTemplate: '/roles-metier', security: "is_granted('ROLE_ADMIN')"),
        new Get(uriTemplate: '/roles-metier/{id}', security: "is_granted('ROLE_ADMIN')"),
        new Post(uriTemplate: '/roles-metier', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(uriTemplate: '/roles-metier/{id}', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(uriTemplate: '/roles-metier/{id}', security: "is_granted('ROLE_SUPER_ADMIN') and object.isSystemTemplate() == false"),
    ]
)]
class RoleMetier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role_metier:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private ?int $atelierId = null;

    #[ORM\Column(length: 50)]
    #[Groups(['role_metier:read', 'role_metier:write', 'user:read'])]
    private string $code;

    #[ORM\Column(length: 120)]
    #[Groups(['role_metier:read', 'role_metier:write', 'user:read'])]
    private string $libelle;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private string $baseRole = 'ROLE_USER';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private ?self $heriteDe = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['role_metier:read'])]
    private bool $isSystemTemplate = false;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private bool $isActive = true;

    /** @var Collection<int, RolePermissionEntry> */
    #[ORM\OneToMany(targetEntity: RolePermissionEntry::class, mappedBy: 'roleMetier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private Collection $permissions;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['role_metier:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['role_metier:read'])]
    private ?int $createdBy = null;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getBaseRole(): string { return $this->baseRole; }
    public function setBaseRole(string $baseRole): static { $this->baseRole = $baseRole; return $this; }

    public function getHeriteDe(): ?self { return $this->heriteDe; }
    public function setHeriteDe(?self $heriteDe): static { $this->heriteDe = $heriteDe; return $this; }

    public function isSystemTemplate(): bool { return $this->isSystemTemplate; }
    public function setIsSystemTemplate(bool $isSystemTemplate): static { $this->isSystemTemplate = $isSystemTemplate; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    /** @return Collection<int, RolePermissionEntry> */
    public function getPermissions(): Collection { return $this->permissions; }

    public function addPermission(RolePermissionEntry $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->setRoleMetier($this);
        }
        return $this;
    }

    public function removePermission(RolePermissionEntry $permission): static
    {
        if ($this->permissions->removeElement($permission)) {
            if ($permission->getRoleMetier() === $this) {
                $permission->setRoleMetier(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function setCreatedBy(?int $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function hasPermission(string $module, string $action): bool
    {
        foreach ($this->permissions as $perm) {
            if ($perm->getModule() === $module && $perm->getAction() === $action && $perm->isGranted()) {
                return true;
            }
        }
        // Check inherited role
        if ($this->heriteDe !== null) {
            return $this->heriteDe->hasPermission($module, $action);
        }
        return false;
    }

    public function getPermissionEntry(string $module, string $action): ?RolePermissionEntry
    {
        foreach ($this->permissions as $perm) {
            if ($perm->getModule() === $module && $perm->getAction() === $action) {
                return $perm;
            }
        }
        if ($this->heriteDe !== null) {
            return $this->heriteDe->getPermissionEntry($module, $action);
        }
        return null;
    }
}
