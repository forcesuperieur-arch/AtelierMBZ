<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'config_atelier')]
#[ApiResource(
    normalizationContext: ['groups' => ['config:read']],
    denormalizationContext: ['groups' => ['config:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
class ConfigAtelier
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] #[Groups(['config:read'])] private ?int $id = null;
    #[ORM\Column(nullable: true)] private ?int $atelierId = null;
    #[ORM\Column(type: 'json', nullable: true)] #[Groups(['config:read', 'config:write'])] private array $featureModules = [
        'dashboard' => true,
        'rdv' => true,
        'rdv_siege' => false,
        'planning' => true,
        'workshop' => true,
        'suivi' => true,
        'clients' => true,
        'or' => true,
        'motos' => true,
        'devis' => false,
        'facturation' => false,
        'stock' => true,
        'mecanicien' => true,
        'absences' => true,
        'admin' => true,
        'tarifs' => true,
        'vo' => false,
        'public_booking' => false,
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

    // LOT 9 — Gardiennage & pièces config
    #[ORM\Column(options: ['default' => 15])] #[Groups(['config:read', 'config:write'])] private int $delaiRelance1JoursOuvres = 15;
    #[ORM\Column(options: ['default' => 30])] #[Groups(['config:read', 'config:write'])] private int $delaiRelance2JoursOuvres = 30;
    #[ORM\Column(options: ['default' => 45])] #[Groups(['config:read', 'config:write'])] private int $delaiProposeGardiennageJoursOuvres = 45;
    #[ORM\Column(options: ['default' => 180])] #[Groups(['config:read', 'config:write'])] private int $delaiProcedureAbandonJoursOuvres = 180;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '5.00'])] #[Groups(['config:read', 'config:write'])] private string $tarifGardiennageJournalier = '5.00';
    #[ORM\Column(options: ['default' => 30])] #[Groups(['config:read', 'config:write'])] private int $garantieTravauxJours = 30;
    #[ORM\Column(type: 'json')] #[Groups(['config:read', 'config:write'])] private array $joursFermetureHebdo = ['sunday'];
    #[ORM\Column(type: 'json')] #[Groups(['config:read', 'config:write'])] private array $datesFermetureExceptionnelles = [];
    #[ORM\Column(type: 'json', nullable: true)] #[Groups(['config:read', 'config:write'])] private ?array $dashboardThresholds = null;

    // Lot A — interrupteurs des notifications client par étape du workflow.
    // Transparence maximale par défaut ; l'atelier peut couper une étape
    // sans redéploiement (clé = code de template, valeur = bool).
    #[ORM\Column(type: 'json', nullable: true)] #[Groups(['config:read', 'config:write'])] private ?array $notificationsEtapes = null;

    // Lot B — check-in obligatoire : la transition 'reception' exige un état
    // des lieux d'entrée SIGNÉ tant que ce toggle est actif (défaut TRUE).
    #[ORM\Column(options: ['default' => true])] #[Groups(['config:read', 'config:write'])] private bool $checkinObligatoire = true;

    // Suivi « moto en atelier » — seuil d'alerte en heures OUVRÉES (les jours de
    // fermeture ne comptent pas) et interrupteur de l'alerte automatique
    // (notification cloche + e-mail récapitulatif). L'onglet de suivi reste
    // toujours accessible, même alerte coupée.
    #[ORM\Column(options: ['default' => 72])] #[Groups(['config:read', 'config:write'])] private int $seuilSejourAtelierHeures = 72;
    #[ORM\Column(options: ['default' => true])] #[Groups(['config:read', 'config:write'])] private bool $alerteSejourAtelierActive = true;

    // Règles métier réglables en administration — auparavant des constantes dans
    // le code (un changement demandait un déploiement). Voir App\Service\ReglesAtelier.
    #[ORM\Column(options: ['default' => 4])] #[Groups(['config:read', 'config:write'])] private int $minPhotosEntree = 4;
    #[ORM\Column(options: ['default' => 4])] #[Groups(['config:read', 'config:write'])] private int $relanceTravauxDelaiHeures = 4;
    #[ORM\Column(options: ['default' => 8])] #[Groups(['config:read', 'config:write'])] private int $relanceHeureMin = 8;
    #[ORM\Column(options: ['default' => 19])] #[Groups(['config:read', 'config:write'])] private int $relanceHeureMax = 19;
    // Intervalle par défaut de la vidange, suggéré au mécano à la restitution
    // (ajustable par OR) et utilisé pour le rappel client (dû dès que l'un des
    // deux seuils est franchi — logique constructeur « X km ou Y mois »).
    #[ORM\Column(options: ['default' => 7000])] #[Groups(['config:read', 'config:write'])] private int $vidangeIntervalleKm = 7000;
    #[ORM\Column(options: ['default' => 12])] #[Groups(['config:read', 'config:write'])] private int $vidangeIntervalleMois = 12;
    #[ORM\Column(type: 'json')] #[Groups(['config:read', 'config:write'])] private array $rappelsRdvJours = [1, 3];
    #[ORM\Column(options: ['default' => 30])] #[Groups(['config:read', 'config:write'])] private int $lienPublicJours = 30;
    #[ORM\Column(options: ['default' => 5])] #[Groups(['config:read', 'config:write'])] private int $essaiPointsMin = 5;
    #[ORM\Column(options: ['default' => 24])] #[Groups(['config:read', 'config:write'])] private int $rappelAlerteHeures = 24;

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
            'rdv_siege' => false,
            'planning' => true,
            'workshop' => true,
            'suivi' => true,
            'clients' => true,
            'or' => true,
            'motos' => true,
            'devis' => false,
            'facturation' => false,
            'stock' => true,
            'mecanicien' => true,
            'absences' => true,
            'admin' => true,
            'tarifs' => true,
            'vo' => false,
        'public_booking' => false,
        ];
    }
    public static function defaultNotificationsEtapes(): array {
        return [
            'rdv_confirmation' => true,
            'rdv_reception' => true,
            'travaux_demarres' => true,
            'attente_pieces' => true,
            'travaux_termines' => true,
            'no_show' => true,
            'demande_relance' => true,
        ];
    }
    public function getNotificationsEtapes(): array { return array_merge(self::defaultNotificationsEtapes(), $this->notificationsEtapes ?? []); }
    public function setNotificationsEtapes(?array $v): static {
        $normalized = [];
        foreach (($v ?? []) as $key => $enabled) {
            $normalized[(string) $key] = !in_array($enabled, [false, 0, '0', 'false'], true);
        }
        $this->notificationsEtapes = $normalized ?: null;
        return $this;
    }
    /** Code inconnu = activé (transparence par défaut) */
    public function isNotificationEtapeEnabled(string $code): bool { return (bool) ($this->getNotificationsEtapes()[$code] ?? true); }

    public function isCheckinObligatoire(): bool { return $this->checkinObligatoire; }
    public function setCheckinObligatoire(bool $v): static { $this->checkinObligatoire = $v; return $this; }
    public function getMinPhotosEntree(): int { return $this->minPhotosEntree; }
    public function setMinPhotosEntree(int $v): static { $this->minPhotosEntree = $v; return $this; }
    public function getRelanceTravauxDelaiHeures(): int { return $this->relanceTravauxDelaiHeures; }
    public function setRelanceTravauxDelaiHeures(int $v): static { $this->relanceTravauxDelaiHeures = $v; return $this; }
    public function getRelanceHeureMin(): int { return $this->relanceHeureMin; }
    public function setRelanceHeureMin(int $v): static { $this->relanceHeureMin = $v; return $this; }
    public function getRelanceHeureMax(): int { return $this->relanceHeureMax; }
    public function getVidangeIntervalleKm(): int { return $this->vidangeIntervalleKm; }
    public function setVidangeIntervalleKm(int $v): static { $this->vidangeIntervalleKm = $v; return $this; }
    public function getVidangeIntervalleMois(): int { return $this->vidangeIntervalleMois; }
    public function setVidangeIntervalleMois(int $v): static { $this->vidangeIntervalleMois = $v; return $this; }
    public function setRelanceHeureMax(int $v): static { $this->relanceHeureMax = $v; return $this; }
    public function getRappelsRdvJours(): array { return $this->rappelsRdvJours; }
    public function setRappelsRdvJours(array $v): static { $this->rappelsRdvJours = array_values($v); return $this; }
    public function getLienPublicJours(): int { return $this->lienPublicJours; }
    public function setLienPublicJours(int $v): static { $this->lienPublicJours = $v; return $this; }
    public function getEssaiPointsMin(): int { return $this->essaiPointsMin; }
    public function setEssaiPointsMin(int $v): static { $this->essaiPointsMin = $v; return $this; }
    public function getRappelAlerteHeures(): int { return $this->rappelAlerteHeures; }
    public function setRappelAlerteHeures(int $v): static { $this->rappelAlerteHeures = $v; return $this; }
    public function getSeuilSejourAtelierHeures(): int { return $this->seuilSejourAtelierHeures; }
    public function setSeuilSejourAtelierHeures(int $v): static { $this->seuilSejourAtelierHeures = $v; return $this; }
    public function isAlerteSejourAtelierActive(): bool { return $this->alerteSejourAtelierActive; }
    public function setAlerteSejourAtelierActive(bool $v): static { $this->alerteSejourAtelierActive = $v; return $this; }

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

    // LOT 9 — Gardiennage & pièces accessors
    public function getDelaiRelance1JoursOuvres(): int { return $this->delaiRelance1JoursOuvres; }
    public function setDelaiRelance1JoursOuvres(int $v): static { $this->delaiRelance1JoursOuvres = $v; return $this; }
    public function getDelaiRelance2JoursOuvres(): int { return $this->delaiRelance2JoursOuvres; }
    public function setDelaiRelance2JoursOuvres(int $v): static { $this->delaiRelance2JoursOuvres = $v; return $this; }
    public function getDelaiProposeGardiennageJoursOuvres(): int { return $this->delaiProposeGardiennageJoursOuvres; }
    public function setDelaiProposeGardiennageJoursOuvres(int $v): static { $this->delaiProposeGardiennageJoursOuvres = $v; return $this; }
    public function getDelaiProcedureAbandonJoursOuvres(): int { return $this->delaiProcedureAbandonJoursOuvres; }
    public function setDelaiProcedureAbandonJoursOuvres(int $v): static { $this->delaiProcedureAbandonJoursOuvres = $v; return $this; }
    public function getTarifGardiennageJournalier(): string { return $this->tarifGardiennageJournalier; }
    public function setTarifGardiennageJournalier(string $v): static { $this->tarifGardiennageJournalier = $v; return $this; }
    public function getGarantieTravauxJours(): int { return $this->garantieTravauxJours; }
    public function setGarantieTravauxJours(int $v): static { $this->garantieTravauxJours = $v; return $this; }
    public function getJoursFermetureHebdo(): array { return $this->joursFermetureHebdo; }
    public function setJoursFermetureHebdo(array $v): static { $this->joursFermetureHebdo = $v; return $this; }
    public function getDatesFermetureExceptionnelles(): array { return $this->datesFermetureExceptionnelles; }
    public function setDatesFermetureExceptionnelles(array $v): static { $this->datesFermetureExceptionnelles = $v; return $this; }
    public function getDashboardThresholds(): array { return $this->dashboardThresholds ?? self::defaultDashboardThresholds(); }
    public function setDashboardThresholds(?array $v): static { $this->dashboardThresholds = $v; return $this; }
    public static function defaultDashboardThresholds(): array {
        return [
            'overrun_warning_minutes' => 15,
            'overrun_critical_minutes' => 45,
            'restitution_warning_minutes' => 15,
            'restitution_critical_minutes' => 45,
            'occupation_target_percent' => 80,
            'occupation_warning_percent' => 60,
            'rendement_target_percent' => 85,
            'rendement_warning_percent' => 70,
            'marge_mo_target_percent' => 60,
            'taux_conversion_devis_target' => 70,
            'delai_attente_pont_warning_minutes' => 30,
        ];
    }
}
