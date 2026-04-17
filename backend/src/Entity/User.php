<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ApiResource(
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN') and object != user"),
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?int $atelierId = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    private string $username;

    #[ORM\Column(length: 200, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $nom = null;

    #[ORM\Column(length: 200)]
    private string $hashedPassword;

    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 50, options: ['default' => 'receptionnaire'])]
    #[Groups(['user:read', 'user:write'])]
    private string $role = 'receptionnaire';

    #[ORM\Column(options: ['default' => 1])]
    #[Groups(['user:read', 'user:write'])]
    private int $isActive = 1;

    #[ORM\ManyToOne(targetEntity: RoleMetier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['user:read', 'user:write'])]
    private ?RoleMetier $roleMetier = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['user:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getUserIdentifier(): string { return $this->username; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $nom): static { $this->nom = $nom; return $this; }

    public function getPassword(): string { return $this->hashedPassword; }
    public function setPassword(string $hashedPassword): static { $this->hashedPassword = $hashedPassword; return $this; }

    public function getHashedPassword(): string { return $this->hashedPassword; }
    public function setHashedPassword(string $hashedPassword): static { $this->hashedPassword = $hashedPassword; return $this; }

    public function getPlainPassword(): ?string { return $this->plainPassword; }
    public function setPlainPassword(?string $plainPassword): static { $this->plainPassword = $plainPassword; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }

    public function getRoleMetier(): ?RoleMetier { return $this->roleMetier; }
    public function setRoleMetier(?RoleMetier $roleMetier): static { $this->roleMetier = $roleMetier; return $this; }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        // Legacy role mapping (backward compatibility)
        $roleMap = [
            'super_admin' => 'ROLE_SUPER_ADMIN',
            'admin' => 'ROLE_ADMIN',
            'vo_manager' => 'ROLE_VO_MANAGER',
            'receptionnaire' => 'ROLE_RECEPTIONNAIRE',
            'mecanicien' => 'ROLE_MECANICIEN',
            'comptable' => 'ROLE_COMPTABLE',
            'service_client' => 'ROLE_SERVICE_CLIENT',
        ];
        if (isset($roleMap[$this->role])) {
            $roles[] = $roleMap[$this->role];
        }
        if ($this->role === 'super_admin') {
            $roles[] = 'ROLE_ADMIN';
        }

        // RoleMetier baseRole
        if ($this->roleMetier !== null && $this->roleMetier->isActive()) {
            $roles[] = $this->roleMetier->getBaseRole();
        }

        return array_unique($roles);
    }

    public function eraseCredentials(): void { $this->plainPassword = null; }
    public function getIsActive(): int { return $this->isActive; }
    public function setIsActive(int $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
