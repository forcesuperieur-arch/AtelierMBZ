<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'role_permission_entries')]
#[ORM\UniqueConstraint(columns: ['role_metier_id', 'module', 'action'])]
class RolePermissionEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role_metier:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RoleMetier::class, inversedBy: 'permissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RoleMetier $roleMetier = null;

    #[ORM\Column(length: 50)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private string $module;

    #[ORM\Column(length: 30)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private string $action;

    #[ORM\Column(length: 30, options: ['default' => 'atelier'])]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private string $scope = 'atelier';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private ?array $conditions = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['role_metier:read', 'role_metier:write'])]
    private bool $granted = true;

    public function getId(): ?int { return $this->id; }

    public function getRoleMetier(): ?RoleMetier { return $this->roleMetier; }
    public function setRoleMetier(?RoleMetier $roleMetier): static { $this->roleMetier = $roleMetier; return $this; }

    public function getModule(): string { return $this->module; }
    public function setModule(string $module): static { $this->module = $module; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getScope(): string { return $this->scope; }
    public function setScope(string $scope): static { $this->scope = $scope; return $this; }

    public function getConditions(): ?array { return $this->conditions; }
    public function setConditions(?array $conditions): static { $this->conditions = $conditions; return $this; }

    public function isGranted(): bool { return $this->granted; }
    public function setGranted(bool $granted): static { $this->granted = $granted; return $this; }
}
