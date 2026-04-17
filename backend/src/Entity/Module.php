<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'modules')]
#[ApiResource(
    normalizationContext: ['groups' => ['module:read']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class Module
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    #[Groups(['module:read'])]
    private string $code;

    #[ORM\Column(length: 120)]
    #[Groups(['module:read'])]
    private string $libelle;

    #[ORM\Column(type: 'json')]
    #[Groups(['module:read'])]
    private array $actions = [];

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getActions(): array { return $this->actions; }
    public function setActions(array $actions): static { $this->actions = $actions; return $this; }
}
