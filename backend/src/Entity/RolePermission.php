<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'role_permissions')]
#[ApiResource(
    normalizationContext: ['groups' => ['role:read']],
    denormalizationContext: ['groups' => ['role:write']],
    operations: [
        new GetCollection(uriTemplate: '/roles', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Get(uriTemplate: '/roles/{role}', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Post(uriTemplate: '/roles', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Patch(uriTemplate: '/roles/{role}', security: "is_granted('ROLE_SUPER_ADMIN')"),
        new Delete(uriTemplate: '/roles/{role}', security: "is_granted('ROLE_SUPER_ADMIN') and object.getRole() != 'super_admin'"),
    ]
)]
class RolePermission
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    #[Groups(['role:read', 'role:write'])]
    private string $role;

    #[ORM\Column(length: 120)]
    #[Groups(['role:read', 'role:write'])]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['role:read', 'role:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text', options: ['default' => '[]'])]
    #[Groups(['role:read', 'role:write'])]
    private string $sectionsJson = '[]';

    #[ORM\Column(type: 'text', options: ['default' => '[]'])]
    #[Groups(['role:read', 'role:write'])]
    private string $permissionsJson = '[]';

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['role:read', 'role:write'])]
    private int $isSystem = 0;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['role:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['role:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTime(); $this->updatedAt = new \DateTime(); }
    public function getRole(): string { return $this->role; }
    public function setRole(string $v): static { $this->role = $v; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getSectionsJson(): string { return $this->sectionsJson; }
    public function setSectionsJson(string $v): static { $this->sectionsJson = $v; return $this; }
    public function getPermissionsJson(): string { return $this->permissionsJson; }
    public function setPermissionsJson(string $v): static { $this->permissionsJson = $v; return $this; }
    public function getIsSystem(): int { return $this->isSystem; }
    public function setIsSystem(int $v): static { $this->isSystem = $v; return $this; }
    public function getSections(): array { return json_decode($this->sectionsJson, true) ?: []; }
    public function getPermissions(): array { return json_decode($this->permissionsJson, true) ?: []; }
}
