<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'cerfa_field_config')]
#[ORM\UniqueConstraint(name: 'uq_cerfa_field', columns: ['cerfa_ref', 'field_key'])]
#[ORM\Index(columns: ['cerfa_ref', 'is_active'], name: 'idx_cerfa_field_ref')]
#[ORM\HasLifecycleCallbacks]
class CerfaFieldConfig
{
    /** Référentiels CERFA supportés */
    public const CERFA_REFS = [
        'cerfa_13751' => 'Cerfa 13751 — DA SIV (declaration achat)',
        'cerfa_13757' => 'Cerfa 13757 — Mandat immatriculation',
        'cerfa_15776' => 'Cerfa 15776 — Certificat de cession',
    ];

    /** Types de champs */
    public const TYPE_TEXT    = 'text';
    public const TYPE_BOXED   = 'boxed';
    public const TYPE_DATE    = 'date';
    public const TYPE_CHECKBOX = 'checkbox';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cerfa_config:read'])]
    private ?int $id = null;

    /** Identifiant du formulaire CERFA : cerfa_13751 | cerfa_13757 | cerfa_15776 */
    #[ORM\Column(length: 30)]
    #[Groups(['cerfa_config:read'])]
    private string $cerfaRef;

    /** Clé unique du champ dans ce formulaire : ex. atelier_nom, vehicle_plaque */
    #[ORM\Column(length: 80)]
    #[Groups(['cerfa_config:read'])]
    private string $fieldKey;

    /** Label lisible pour l'interface d'administration */
    #[ORM\Column(length: 200)]
    #[Groups(['cerfa_config:read'])]
    private string $label;

    /** Position X en mm depuis le bord gauche de la page */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    #[Groups(['cerfa_config:read'])]
    private string $x;

    /** Position Y en mm depuis le bord haut de la page */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    #[Groups(['cerfa_config:read'])]
    private string $y;

    /** Largeur de la zone en mm (0 = pas de contrainte) */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, options: ['default' => 0])]
    #[Groups(['cerfa_config:read'])]
    private string $width = '0';

    /** Taille de police en points */
    #[ORM\Column(type: 'decimal', precision: 4, scale: 1, options: ['default' => 8])]
    #[Groups(['cerfa_config:read'])]
    private string $fontSize = '8';

    /** Type : text | boxed | date | checkbox */
    #[ORM\Column(length: 20, options: ['default' => 'text'])]
    #[Groups(['cerfa_config:read'])]
    private string $fieldType = self::TYPE_TEXT;

    /** Description facultative pour l'admin */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cerfa_config:read'])]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['cerfa_config:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['cerfa_config:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['cerfa_config:read'])]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCerfaRef(): string { return $this->cerfaRef; }
    public function setCerfaRef(string $v): static { $this->cerfaRef = $v; return $this; }

    public function getFieldKey(): string { return $this->fieldKey; }
    public function setFieldKey(string $v): static { $this->fieldKey = $v; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getX(): string { return $this->x; }
    public function setX(string $v): static { $this->x = $v; return $this; }

    public function getY(): string { return $this->y; }
    public function setY(string $v): static { $this->y = $v; return $this; }

    public function getWidth(): string { return $this->width; }
    public function setWidth(string $v): static { $this->width = $v; return $this; }

    public function getFontSize(): string { return $this->fontSize; }
    public function setFontSize(string $v): static { $this->fontSize = $v; return $this; }

    public function getFieldType(): string { return $this->fieldType; }
    public function setFieldType(string $v): static { $this->fieldType = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}
