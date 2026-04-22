<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'rendez_vous')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'rendez-vous',
    normalizationContext: ['groups' => ['rdv:read']],
    denormalizationContext: ['groups' => ['rdv:write']],
    operations: [
        new GetCollection(uriTemplate: '/rendez-vous'),
        new Get(uriTemplate: '/rendez-vous/{id}'),
        new Put(uriTemplate: '/rendez-vous/{id}'),
        new Delete(uriTemplate: '/rendez-vous/{id}'),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['statut' => 'exact', 'client.id' => 'exact', 'mecanicien.id' => 'exact', 'pont.id' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['dateRdv'])]
#[ApiFilter(OrderFilter::class, properties: ['dateRdv', 'heureRdv', 'createdAt'])]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['rdv:read'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'client_id')]
    #[Groups(['rdv:read', 'rdv:write', 'ordre:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Vehicule::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'vehicule_id')]
    #[Groups(['rdv:read', 'rdv:write', 'ordre:read'])]
    private ?Vehicule $vehicule = null;

    #[ORM\Column(type: 'date')]
    #[Groups(['rdv:read', 'rdv:write'])]
    private \DateTimeInterface $dateRdv;

    #[ORM\Column(type: 'time')]
    #[Groups(['rdv:read', 'rdv:write'])]
    private \DateTimeInterface $heureRdv;

    #[ORM\Column(length: 200)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private string $typeIntervention;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $prixEstime = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $prixFinal = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?int $tempsEstime = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?int $tempsFinal = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['rdv:read'])]
    private ?\DateTimeInterface $heureDebutTravail = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['rdv:read'])]
    private ?\DateTimeInterface $heureFinTravail = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['rdv:read'])]
    private ?int $tempsEffectifMinutes = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $etatVehicule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $photosEtat = null;

    #[ORM\ManyToOne(targetEntity: Pont::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'pont_id', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?Pont $pont = null;

    #[ORM\ManyToOne(targetEntity: Mecanicien::class, inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(name: 'mecanicien_id', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?Mecanicien $mecanicien = null;

    #[ORM\OneToOne(mappedBy: 'rendezVous', targetEntity: RapportTechnicien::class, cascade: ['persist', 'remove'])]
    #[Groups(['rdv:read'])]
    private ?RapportTechnicien $rapportTechnicien = null;

    #[ORM\OneToOne(mappedBy: 'rendezVous', targetEntity: EssaiRoutier::class, cascade: ['persist'])]
    #[Groups(['rdv:read'])]
    private ?EssaiRoutier $essaiRoutier = null;

    #[ORM\Column(length: 50, options: ['default' => 'en_attente'])]
    #[Groups(['rdv:read', 'ordre:read'])]
    private string $statut = 'en_attente';

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $motifAnnulation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $commentaireAnnulation = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Groups(['rdv:read'])]
    private ?string $tokenSuivi = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['rdv:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['rdv:read'])]
    private \DateTimeInterface $updatedAt;

/** @var Collection<int, DemandeTravauxSupp> */
    #[ORM\OneToMany(targetEntity: DemandeTravauxSupp::class, mappedBy: 'rendezVous', cascade: ['persist', 'remove'])]
    #[Groups(['rdv:read'])]
    private Collection $demandesTravauxSupp;

    /** @var Collection<int, OrdreReparation> */
    #[ORM\OneToMany(targetEntity: OrdreReparation::class, mappedBy: 'rendezVous', cascade: ['persist', 'remove'])]
    #[Groups(['rdv:read'])]
    private Collection $ordresReparation;

    /** @var Collection<int, PhotoIntervention> */
    #[ORM\OneToMany(targetEntity: PhotoIntervention::class, mappedBy: 'rendezVous', cascade: ['persist', 'remove'])]
    private Collection $photosIntervention;

    /** @var Collection<int, PieceUtilisee> */
    #[ORM\OneToMany(targetEntity: PieceUtilisee::class, mappedBy: 'rendezVous', cascade: ['persist', 'remove'])]
    private Collection $piecesUtilisees;

    // LOT 9 — Stockage & Gardiennage
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $emplacementStockage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoStockageFilename = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $misEnStockageAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $misEnStockagePar = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $gardiennageDebutAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $gardiennageDebutPar = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $gardiennageMotif = null;

    // [SPRINT-4] I4 — source du RDV : null = interne, 'web' = prise en ligne publique
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['rdv:read', 'rdv:write'])]
    private ?string $source = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->tokenSuivi = bin2hex(random_bytes(32));
        $this->demandesTravauxSupp = new ArrayCollection();
        $this->ordresReparation = new ArrayCollection();
        $this->photosIntervention = new ArrayCollection();
        $this->piecesUtilisees = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $atelierId): static { $this->atelierId = $atelierId; return $this; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }
    public function getVehicule(): ?Vehicule { return $this->vehicule; }
    public function setVehicule(?Vehicule $vehicule): static { $this->vehicule = $vehicule; return $this; }
    public function getDateRdv(): \DateTimeInterface { return $this->dateRdv; }
    public function setDateRdv(\DateTimeInterface $dateRdv): static { $this->dateRdv = $dateRdv; return $this; }
    public function getHeureRdv(): \DateTimeInterface { return $this->heureRdv; }
    public function setHeureRdv(\DateTimeInterface $heureRdv): static { $this->heureRdv = $heureRdv; return $this; }
    public function getTypeIntervention(): string { return $this->typeIntervention; }
    public function setTypeIntervention(string $typeIntervention): static { $this->typeIntervention = $typeIntervention; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): static { $this->commentaire = $commentaire; return $this; }
    public function getPrixEstime(): ?string { return $this->prixEstime; }
    public function setPrixEstime(?string $prixEstime): static { $this->prixEstime = $prixEstime; return $this; }
    public function getPrixFinal(): ?string { return $this->prixFinal; }
    public function setPrixFinal(?string $prixFinal): static { $this->prixFinal = $prixFinal; return $this; }
    public function getTempsEstime(): ?int { return $this->tempsEstime; }
    public function setTempsEstime(?int $tempsEstime): static { $this->tempsEstime = $tempsEstime; return $this; }
    public function getTempsFinal(): ?int { return $this->tempsFinal; }
    public function setTempsFinal(?int $tempsFinal): static { $this->tempsFinal = $tempsFinal; return $this; }
    public function getHeureDebutTravail(): ?\DateTimeInterface { return $this->heureDebutTravail; }
    public function setHeureDebutTravail(?\DateTimeInterface $v): static { $this->heureDebutTravail = $v; return $this; }
    public function getHeureFinTravail(): ?\DateTimeInterface { return $this->heureFinTravail; }
    public function setHeureFinTravail(?\DateTimeInterface $v): static { $this->heureFinTravail = $v; return $this; }
    public function getTempsEffectifMinutes(): ?int { return $this->tempsEffectifMinutes; }
    public function setTempsEffectifMinutes(?int $v): static { $this->tempsEffectifMinutes = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $kilometrage): static { $this->kilometrage = $kilometrage; return $this; }
    public function getEtatVehicule(): ?string { return $this->etatVehicule; }
    public function setEtatVehicule(?string $etatVehicule): static { $this->etatVehicule = $etatVehicule; return $this; }
    public function getPhotosEtat(): ?string { return $this->photosEtat; }
    public function setPhotosEtat(?string $photosEtat): static { $this->photosEtat = $photosEtat; return $this; }
    public function getPont(): ?Pont { return $this->pont; }
    public function setPont(?Pont $pont): static { $this->pont = $pont; return $this; }
    public function getMecanicien(): ?Mecanicien { return $this->mecanicien; }
    public function setMecanicien(?Mecanicien $mecanicien): static { $this->mecanicien = $mecanicien; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getTokenSuivi(): ?string { return $this->tokenSuivi; }
    public function setTokenSuivi(?string $tokenSuivi): static { $this->tokenSuivi = $tokenSuivi; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function getRapportTechnicien(): ?RapportTechnicien { return $this->rapportTechnicien; }
    public function setRapportTechnicien(?RapportTechnicien $rapportTechnicien): static {
        $this->rapportTechnicien = $rapportTechnicien;
        if ($rapportTechnicien !== null && $rapportTechnicien->getRendezVous() !== $this) {
            $rapportTechnicien->setRendezVous($this);
        }
        return $this;
    }
    public function getEssaiRoutier(): ?EssaiRoutier { return $this->essaiRoutier; }
    public function setEssaiRoutier(?EssaiRoutier $essaiRoutier): static {
        $this->essaiRoutier = $essaiRoutier;
        if ($essaiRoutier !== null && $essaiRoutier->getRendezVous() !== $this) {
            $essaiRoutier->setRendezVous($this);
        }
        return $this;
    }
    public function getDemandesTravauxSupp(): Collection { return $this->demandesTravauxSupp; }
    public function getOrdresReparation(): Collection { return $this->ordresReparation; }
    public function getPhotosIntervention(): Collection { return $this->photosIntervention; }
    public function getPiecesUtilisees(): Collection { return $this->piecesUtilisees; }
    public function getMotifAnnulation(): ?string { return $this->motifAnnulation; }
    public function setMotifAnnulation(?string $v): static { $this->motifAnnulation = $v; return $this; }
    public function getCommentaireAnnulation(): ?string { return $this->commentaireAnnulation; }
    public function setCommentaireAnnulation(?string $v): static { $this->commentaireAnnulation = $v; return $this; }

    // LOT 9 — Stockage & Gardiennage accessors
    public function getEmplacementStockage(): ?string { return $this->emplacementStockage; }
    public function setEmplacementStockage(?string $v): static { $this->emplacementStockage = $v; return $this; }
    public function getPhotoStockageFilename(): ?string { return $this->photoStockageFilename; }
    public function setPhotoStockageFilename(?string $v): static { $this->photoStockageFilename = $v; return $this; }
    public function getMisEnStockageAt(): ?\DateTimeInterface { return $this->misEnStockageAt; }
    public function setMisEnStockageAt(?\DateTimeInterface $v): static { $this->misEnStockageAt = $v; return $this; }
    public function getMisEnStockagePar(): ?int { return $this->misEnStockagePar; }
    public function setMisEnStockagePar(?int $v): static { $this->misEnStockagePar = $v; return $this; }
    public function getGardiennageDebutAt(): ?\DateTimeInterface { return $this->gardiennageDebutAt; }
    public function setGardiennageDebutAt(?\DateTimeInterface $v): static { $this->gardiennageDebutAt = $v; return $this; }
    public function getGardiennageDebutPar(): ?int { return $this->gardiennageDebutPar; }
    public function setGardiennageDebutPar(?int $v): static { $this->gardiennageDebutPar = $v; return $this; }
    public function getGardiennageMotif(): ?string { return $this->gardiennageMotif; }
    public function setGardiennageMotif(?string $v): static { $this->gardiennageMotif = $v; return $this; }

    // [SPRINT-4] I4
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }
}
