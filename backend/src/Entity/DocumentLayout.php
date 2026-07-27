<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * En-tête personnalisé d'un document PDF, composé dans le designer admin.
 *
 * La liste des codes valides n'est plus dupliquée ici : elle vient de
 * App\Service\PdfTemplateRegistry, seule source de vérité de l'inventaire des
 * documents. La copie locale avait divergé du disque (elle annonçait un
 * « rapport_intervention » sans template).
 */
#[ORM\Entity]
#[ORM\Table(name: 'document_layouts')]
#[ORM\UniqueConstraint(name: 'uq_doc_layout', columns: ['atelier_id', 'code'])]
class DocumentLayout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 200)]
    private string $label;

    #[ORM\Column(type: 'json')]
    private array $layoutJson = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $v): static { $this->code = $v; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getLayoutJson(): array { return $this->layoutJson; }
    public function setLayoutJson(array $v): static { $this->layoutJson = $v; return $this; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $v): static { $this->isDefault = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }
}
