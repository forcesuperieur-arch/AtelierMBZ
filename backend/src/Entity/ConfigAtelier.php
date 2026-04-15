<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'config_atelier')]
#[ApiResource(
    normalizationContext: ['groups' => ['config:read']],
    denormalizationContext: ['groups' => ['config:write']],
)]
class ConfigAtelier
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['config:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(type: 'json', nullable: true)] #[Groups(['config:read', 'config:write'])] private array $featureModules = [
        'dashboard' => true,
        'rdv' => true,
        'planning' => true,
        'workshop' => true,
        'suivi' => true,
        'clients' => true,
        'or' => true,
        'motos' => true,
        'devis' => true,
        'facturation' => true,
        'stock' => true,
        'mecanicien' => true,
        'absences' => true,
        'admin' => true,
        'tarifs' => true,
    ];
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '65.00'])] #[Groups(['config:read', 'config:write'])] private string $tauxHoraireMoStandard = '65.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '85.00'])] #[Groups(['config:read', 'config:write'])] private string $tauxHoraireMoComplexe = '85.00';
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '95.00'])] #[Groups(['config:read', 'config:write'])] private string $tauxHoraireMoExpert = '95.00';
    #[ORM\Column(type: 'float', options: ['default' => 30.0])] #[Groups(['config:read', 'config:write'])] private float $margePiecesStandard = 30.0;
    #[ORM\Column(type: 'float', options: ['default' => 50.0])] #[Groups(['config:read', 'config:write'])] private float $margePiecesConsommable = 50.0;
    #[ORM\Column(type: 'float', options: ['default' => 25.0])] #[Groups(['config:read', 'config:write'])] private float $margePiecesPneumatique = 25.0;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '25.00'])] #[Groups(['config:read', 'config:write'])] private string $forfaitMoMinimum = '25.00';
    #[ORM\Column(type: 'float', options: ['default' => 20.0])] #[Groups(['config:read', 'config:write'])] private float $tvaMoTaux = 20.0;
    #[ORM\Column(type: 'float', options: ['default' => 20.0])] #[Groups(['config:read', 'config:write'])] private float $tvaPiecesTaux = 20.0;
    #[ORM\Column(options: ['default' => 30])] #[Groups(['config:read', 'config:write'])] private int $validiteDevisJours = 30;
    #[ORM\Column(type: 'float', options: ['default' => 30.0])] #[Groups(['config:read', 'config:write'])] private float $accomptePourcentage = 30.0;
    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])] #[Groups(['config:read'])] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getFeatureModules(): array { return $this->featureModules ?: self::defaultFeatureModules(); }
    public function setFeatureModules(?array $v): static {
        $normalized = self::defaultFeatureModules();
        foreach (($v ?? []) as $key => $enabled) {
            $normalized[(string) $key] = !in_array($enabled, [false, 0, '0', 'false'], true);
        }
        $this->featureModules = $normalized;
        return $this;
    }
    public static function defaultFeatureModules(): array {
        return [
            'dashboard' => true,
            'rdv' => true,
            'planning' => true,
            'workshop' => true,
            'suivi' => true,
            'clients' => true,
            'or' => true,
            'motos' => true,
            'devis' => true,
            'facturation' => true,
            'stock' => true,
            'mecanicien' => true,
            'absences' => true,
            'admin' => true,
            'tarifs' => true,
        ];
    }
    public function getTauxHoraireMoStandard(): string { return $this->tauxHoraireMoStandard; }
    public function setTauxHoraireMoStandard(string $v): static { $this->tauxHoraireMoStandard = $v; return $this; }
    public function getTauxHoraireMoComplexe(): string { return $this->tauxHoraireMoComplexe; }
    public function setTauxHoraireMoComplexe(string $v): static { $this->tauxHoraireMoComplexe = $v; return $this; }
    public function getTauxHoraireMoExpert(): string { return $this->tauxHoraireMoExpert; }
    public function setTauxHoraireMoExpert(string $v): static { $this->tauxHoraireMoExpert = $v; return $this; }
    public function getMargePiecesStandard(): float { return $this->margePiecesStandard; }
    public function setMargePiecesStandard(float $v): static { $this->margePiecesStandard = $v; return $this; }
    public function getMargePiecesConsommable(): float { return $this->margePiecesConsommable; }
    public function setMargePiecesConsommable(float $v): static { $this->margePiecesConsommable = $v; return $this; }
    public function getMargePiecesPneumatique(): float { return $this->margePiecesPneumatique; }
    public function setMargePiecesPneumatique(float $v): static { $this->margePiecesPneumatique = $v; return $this; }
    public function getForfaitMoMinimum(): string { return $this->forfaitMoMinimum; }
    public function setForfaitMoMinimum(string $v): static { $this->forfaitMoMinimum = $v; return $this; }
    public function getTvaMoTaux(): float { return $this->tvaMoTaux; }
    public function setTvaMoTaux(float $v): static { $this->tvaMoTaux = $v; return $this; }
    public function getTvaPiecesTaux(): float { return $this->tvaPiecesTaux; }
    public function setTvaPiecesTaux(float $v): static { $this->tvaPiecesTaux = $v; return $this; }
    public function getValiditeDevisJours(): int { return $this->validiteDevisJours; }
    public function setValiditeDevisJours(int $v): static { $this->validiteDevisJours = $v; return $this; }
    public function getAccomptePourcentage(): float { return $this->accomptePourcentage; }
    public function setAccomptePourcentage(float $v): static { $this->accomptePourcentage = $v; return $this; }
}
