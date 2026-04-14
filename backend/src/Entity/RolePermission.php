<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'role_permissions')]
class RolePermission
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $role;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', options: ['default' => '[]'])]
    private string $sectionsJson = '[]';

    #[ORM\Column(type: 'text', options: ['default' => '[]'])]
    private string $permissionsJson = '[]';

    #[ORM\Column(options: ['default' => 0])]
    private int $isSystem = 0;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
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
