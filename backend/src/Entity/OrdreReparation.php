<?php
namespace App\Entity;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity] #[ORM\Table(name: 'ordres_reparation')]
#[ApiResource(
    shortName: 'ordres-reparation',
    normalizationContext: ['groups' => ['ordre:read']],
    denormalizationContext: ['groups' => ['ordre:write']],
    // L'OR est un document légal scellé : sa création/mutation/suppression passe
    // EXCLUSIVEMENT par les flux métier contrôlés (Companion réception, décision
    // travaux supp, rectification). On n'expose donc que la LECTURE via API Platform.
    // Les opérations Post/Put/Delete génériques ont été retirées : non utilisées par
    // le front, sans garde d'état ni de rôle, elles permettaient d'éditer le contenu
    // d'un OR après scellé de réception (travaux, numéro, réaffectation de RDV) et
    // de SUPPRIMER un OR signé (aucun garde-fou preRemove). Voir OrdreReparationPolicy.
    operations: [
        new GetCollection(uriTemplate: '/ordres-reparation'),
        new Get(uriTemplate: '/ordres-reparation/{id}'),
    ],
)]
class OrdreReparation
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    #[Groups(['ordre:read', 'rdv:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RendezVous::class, inversedBy: 'ordresReparation')] #[ORM\JoinColumn(name: 'rendez_vous_id', nullable: false)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private RendezVous $rendezVous;

    // Discriminant multi-atelier : renseigné depuis le RDV à la création. Fait
    // participer l'OR au TenantFilter global (isolation), comme les autres entités.
    #[ORM\Column(nullable: true)]
    private ?int $atelierId = null;

    #[ORM\Column(length: 50)]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $numeroOr;

    #[ORM\Column(length: 50, options: ['default' => 'initial'])]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $typeOr = 'initial';

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?int $kilometrage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $etatVehicule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $mechanicNotes = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $mechanicNotesUpdatedAt = null;

    #[ORM\Column(type: 'text', options: ['default' => '{}'])]
    #[Groups(['ordre:read', 'ordre:write'])]
    private string $mechanicCheckup = '{}';

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $mechanicCheckupUpdatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $travaux = null;

    /**
     * Montant total ESTIMÉ (indicatif) reporté du RDV à la création de l'OR.
     * Figé au scellé de réception (inclus dans le snapshot/hash). Pas une facture.
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $montantEstime = null;

    #[ORM\ManyToOne(targetEntity: DemandeTravauxSupp::class)] #[ORM\JoinColumn(name: 'demande_travaux_supp_id', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?DemandeTravauxSupp $demandeTravauxSupp = null;

    // ─── Signatures ───

    /** Signature client lors de la réception (PDA) */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureClient = null;

    /** Signature atelier lors de la réception (PDA) */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureAtelierReception = null;

    /** Signature mécanicien après intervention */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureMecanicien = null;

    /** Signature client lors de la restitution */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signatureClientRestitution = null;

    // ─── Champs rapport d'intervention ───

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $travauxRealises = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?array $alertes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $recommandations = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?string $garantie = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?int $kilometrageRestitution = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?int $prochaineRevisionKm = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['ordre:read', 'ordre:write'])]
    private ?\DateTimeInterface $prochaineRevisionDate = null;

    /** Anti-doublon du rappel de vidange (km ou date) : posé une fois notifié, jamais renvoyé. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $vidangeNotifieeAt = null;

    #[ORM\OneToOne(targetEntity: EssaiRoutier::class)]
    #[ORM\JoinColumn(name: 'essai_routier_id', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?EssaiRoutier $essaiRoutier = null;

    // ─── Statut ───

    #[ORM\Column(length: 50, options: ['default' => 'brouillon'])]
    #[Groups(['ordre:read', 'ordre:write', 'rdv:read'])]
    private string $statut = 'brouillon';

    // ─── Traçabilité signatures ───

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $signeMecanicienAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read'])]
    private ?int $signeMecanicienId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $signeReceptionnisteAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $signeClientRestitutionAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $signedSnapshot = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $signedHash = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $signedAt = null;

    // Scellé FINAL (restitution) : empreinte complète couvrant le travail réalisé
    // et toutes les signatures. Distinct du scellé de réception (signedSnapshot/
    // signedHash), qui reste préservé. Posé une fois, jamais modifié (FreezeListener).
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $finalSnapshot = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $finalHash = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $finalizedAt = null;

    // Nom du PDF archivé immuable (aléatoire, hors webroot), jamais régénéré.
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $pdfArchiveName = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $signedIp = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $signedUserAgent = null;

    #[ORM\ManyToOne(targetEntity: OrdreReparation::class)]
    #[ORM\JoinColumn(name: 'rectified_from_id', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?OrdreReparation $rectifiedFrom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $motifRectification = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ordre:read'])]
    private ?int $rectifiedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['ordre:read'])]
    private ?\DateTimeInterface $rectifiedAt = null;

    // --- RGPD: Snapshot fields ---
    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapClientNom = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapClientPrenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculePlaque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculeMarque = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['ordre:read'])]
    private ?string $snapVehiculeModele = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['ordre:read'])]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTime(); }

    public function snapshotFromRdv(): void {
        $rdv = $this->rendezVous;
        if ($rdv->getClient() && !$this->snapClientNom) {
            $this->snapClientNom = $rdv->getClient()->getNom();
            $this->snapClientPrenom = $rdv->getClient()->getPrenom();
        }
        if ($rdv->getVehicule() && !$this->snapVehiculePlaque) {
            $this->snapVehiculePlaque = $rdv->getVehicule()->getPlaque();
            $this->snapVehiculeMarque = $rdv->getVehicule()->getMarque();
            $this->snapVehiculeModele = $rdv->getVehicule()->getModele();
        }
        // Reporter les prestations réservées dans les travaux prévus + le total
        // estimé (indicatif), tant que le mécano n'a pas déjà saisi de travaux.
        if ($this->travaux === null || $this->travaux === '') {
            $lignes = [];
            foreach ($rdv->getPrestationsSnapshot() ?? [] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $ttc = (float) ($p['prix_ttc'] ?? 0);
                $ht = (float) ($p['prix_ht'] ?? 0);
                $eff = $ttc > 0 ? $ttc : $ht;
                $designation = (string) ($p['designation'] ?? 'Prestation');
                $lignes[] = $eff > 0
                    ? sprintf('%s — %s € (estim.)', $designation, number_format($eff, 2, ',', ' '))
                    : $designation;
            }
            if ($lignes) {
                $this->travaux = implode("\n", $lignes);
            } elseif ($rdv->getTypeIntervention()) {
                $this->travaux = $rdv->getTypeIntervention();
            }
        }
        if ($this->montantEstime === null && $rdv->getPrixEstime() !== null) {
            $this->montantEstime = $rdv->getPrixEstime();
        }
    }

    // ─── Getters / Setters ───

    public function getId(): ?int { return $this->id; }
    public function getRendezVous(): RendezVous { return $this->rendezVous; }
    public function setRendezVous(RendezVous $v): static { $this->rendezVous = $v; return $this; }
    public function getAtelierId(): ?int { return $this->atelierId; }
    public function setAtelierId(?int $v): static { $this->atelierId = $v; return $this; }
    public function getNumeroOr(): string { return $this->numeroOr; }
    public function setNumeroOr(string $v): static { $this->numeroOr = $v; return $this; }
    public function getTypeOr(): string { return $this->typeOr; }
    public function setTypeOr(string $v): static { $this->typeOr = $v; return $this; }
    public function getKilometrage(): ?int { return $this->kilometrage; }
    public function setKilometrage(?int $v): static { $this->kilometrage = $v; return $this; }
    public function getEtatVehicule(): ?string { return $this->etatVehicule; }
    public function setEtatVehicule(?string $v): static { $this->etatVehicule = $v; return $this; }
    public function getMechanicNotes(): ?string { return $this->mechanicNotes; }
    public function setMechanicNotes(?string $v): static {
        $this->mechanicNotes = $v;
        $this->mechanicNotesUpdatedAt = new \DateTime();
        return $this;
    }
    public function getMechanicNotesUpdatedAt(): ?\DateTimeInterface { return $this->mechanicNotesUpdatedAt; }
    public function getMechanicCheckup(): array { return json_decode($this->mechanicCheckup, true) ?: []; }
    public function setMechanicCheckup(array $v): static {
        $this->mechanicCheckup = $v === []
            ? '{}'
            : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $this->mechanicCheckupUpdatedAt = new \DateTime();
        return $this;
    }
    public function getMechanicCheckupUpdatedAt(): ?\DateTimeInterface { return $this->mechanicCheckupUpdatedAt; }
    public function getTravaux(): ?string { return $this->travaux; }
    public function setTravaux(?string $v): static { $this->travaux = $v; return $this; }
    public function getMontantEstime(): ?string { return $this->montantEstime; }
    public function setMontantEstime(?string $v): static { $this->montantEstime = $v; return $this; }
    public function getDemandeTravauxSupp(): ?DemandeTravauxSupp { return $this->demandeTravauxSupp; }
    public function setDemandeTravauxSupp(?DemandeTravauxSupp $v): static { $this->demandeTravauxSupp = $v; return $this; }

    // --- Signatures ---
    public function getSignatureClient(): ?string { return $this->signatureClient; }
    public function setSignatureClient(?string $v): static { $this->signatureClient = $v; return $this; }
    public function getSignatureAtelierReception(): ?string { return $this->signatureAtelierReception; }
    public function setSignatureAtelierReception(?string $v): static { $this->signatureAtelierReception = $v; return $this; }
    public function getSignatureMecanicien(): ?string { return $this->signatureMecanicien; }
    public function setSignatureMecanicien(?string $v): static { $this->signatureMecanicien = $v; return $this; }
    public function getSignatureClientRestitution(): ?string { return $this->signatureClientRestitution; }
    public function setSignatureClientRestitution(?string $v): static { $this->signatureClientRestitution = $v; return $this; }

    // --- Rapport fields ---
    public function getTravauxRealises(): ?string { return $this->travauxRealises; }
    public function setTravauxRealises(?string $v): static { $this->travauxRealises = $v; return $this; }
    public function getAlertes(): ?array { return $this->alertes; }
    public function setAlertes(?array $v): static { $this->alertes = $v; return $this; }
    public function getRecommandations(): ?string { return $this->recommandations; }
    public function setRecommandations(?string $v): static { $this->recommandations = $v; return $this; }
    public function getGarantie(): ?string { return $this->garantie; }
    public function setGarantie(?string $v): static { $this->garantie = $v; return $this; }
    public function getKilometrageRestitution(): ?int { return $this->kilometrageRestitution; }
    public function setKilometrageRestitution(?int $v): static { $this->kilometrageRestitution = $v; return $this; }
    public function getProchaineRevisionKm(): ?int { return $this->prochaineRevisionKm; }
    public function setProchaineRevisionKm(?int $v): static { $this->prochaineRevisionKm = $v; return $this; }
    public function getProchaineRevisionDate(): ?\DateTimeInterface { return $this->prochaineRevisionDate; }
    public function setProchaineRevisionDate(?\DateTimeInterface $v): static { $this->prochaineRevisionDate = $v; return $this; }
    public function getVidangeNotifieeAt(): ?\DateTimeImmutable { return $this->vidangeNotifieeAt; }
    public function setVidangeNotifieeAt(?\DateTimeImmutable $v): static { $this->vidangeNotifieeAt = $v; return $this; }
    public function getEssaiRoutier(): ?EssaiRoutier { return $this->essaiRoutier; }
    public function setEssaiRoutier(?EssaiRoutier $v): static { $this->essaiRoutier = $v; return $this; }

    // --- Statut & Signature fields ---
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }
    public function getSigneMecanicienAt(): ?\DateTimeInterface { return $this->signeMecanicienAt; }
    public function setSigneMecanicienAt(?\DateTimeInterface $v): static { $this->signeMecanicienAt = $v; return $this; }
    public function getSigneMecanicienId(): ?int { return $this->signeMecanicienId; }
    public function setSigneMecanicienId(?int $v): static { $this->signeMecanicienId = $v; return $this; }
    public function getSigneReceptionnisteAt(): ?\DateTimeInterface { return $this->signeReceptionnisteAt; }
    public function setSigneReceptionnisteAt(?\DateTimeInterface $v): static { $this->signeReceptionnisteAt = $v; return $this; }
    public function getSigneClientRestitutionAt(): ?\DateTimeInterface { return $this->signeClientRestitutionAt; }
    public function setSigneClientRestitutionAt(?\DateTimeInterface $v): static { $this->signeClientRestitutionAt = $v; return $this; }
    public function getSignedSnapshot(): ?array { return $this->signedSnapshot; }
    public function setSignedSnapshot(?array $v): static { $this->signedSnapshot = $v; return $this; }
    public function getSignedHash(): ?string { return $this->signedHash; }
    public function setSignedHash(?string $v): static { $this->signedHash = $v; return $this; }
    public function getSignedAt(): ?\DateTimeInterface { return $this->signedAt; }
    public function setSignedAt(?\DateTimeInterface $v): static { $this->signedAt = $v; return $this; }
    public function getFinalSnapshot(): ?array { return $this->finalSnapshot; }
    public function setFinalSnapshot(?array $v): static { $this->finalSnapshot = $v; return $this; }
    public function getFinalHash(): ?string { return $this->finalHash; }
    public function setFinalHash(?string $v): static { $this->finalHash = $v; return $this; }
    public function getFinalizedAt(): ?\DateTimeInterface { return $this->finalizedAt; }
    public function setFinalizedAt(?\DateTimeInterface $v): static { $this->finalizedAt = $v; return $this; }
    public function getPdfArchiveName(): ?string { return $this->pdfArchiveName; }
    public function setPdfArchiveName(?string $v): static { $this->pdfArchiveName = $v; return $this; }
    public function getSignedIp(): ?string { return $this->signedIp; }
    public function setSignedIp(?string $v): static { $this->signedIp = $v; return $this; }
    public function getSignedUserAgent(): ?string { return $this->signedUserAgent; }
    public function setSignedUserAgent(?string $v): static { $this->signedUserAgent = $v; return $this; }
    public function getRectifiedFrom(): ?OrdreReparation { return $this->rectifiedFrom; }
    public function setRectifiedFrom(?OrdreReparation $v): static { $this->rectifiedFrom = $v; return $this; }
    public function getMotifRectification(): ?string { return $this->motifRectification; }
    public function setMotifRectification(?string $v): static { $this->motifRectification = $v; return $this; }
    public function getRectifiedBy(): ?int { return $this->rectifiedBy; }
    public function setRectifiedBy(?int $v): static { $this->rectifiedBy = $v; return $this; }
    public function getRectifiedAt(): ?\DateTimeInterface { return $this->rectifiedAt; }
    public function setRectifiedAt(?\DateTimeInterface $v): static { $this->rectifiedAt = $v; return $this; }

    /**
     * L'OR est signé si au moins le client a signé la réception
     * et le statut est au-delà de brouillon.
     */
    public function isSigned(): bool
    {
        return in_array($this->statut, ['reception_signee', 'intervention_signee', 'signe', 'execute', 'termine'], true);
    }

    /**
     * L'OR est figé si signé par le client restitution ou rectifié.
     */
    public function isFrozen(): bool
    {
        return in_array($this->statut, ['signe', 'execute', 'termine', 'rectifie'], true);
    }

    /**
     * La réception est signée si client + atelier ont signé.
     */
    public function isReceptionSigned(): bool
    {
        return $this->signatureClient !== null && $this->signatureAtelierReception !== null;
    }

    /**
     * L'intervention est signée si le mécanicien a signé.
     */
    public function isInterventionSigned(): bool
    {
        return $this->signatureMecanicien !== null;
    }

    /**
     * La restitution est signée si le client a signé.
     */
    public function isRestitutionSigned(): bool
    {
        return $this->signatureClientRestitution !== null;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    // --- RGPD snapshot getters/setters ---
    public function getSnapClientNom(): ?string { return $this->snapClientNom; }
    public function setSnapClientNom(?string $v): static { $this->snapClientNom = $v; return $this; }
    public function getSnapClientPrenom(): ?string { return $this->snapClientPrenom; }
    public function setSnapClientPrenom(?string $v): static { $this->snapClientPrenom = $v; return $this; }
    public function getSnapVehiculePlaque(): ?string { return $this->snapVehiculePlaque; }
    public function setSnapVehiculePlaque(?string $v): static { $this->snapVehiculePlaque = $v; return $this; }
    public function getSnapVehiculeMarque(): ?string { return $this->snapVehiculeMarque; }
    public function setSnapVehiculeMarque(?string $v): static { $this->snapVehiculeMarque = $v; return $this; }
    public function getSnapVehiculeModele(): ?string { return $this->snapVehiculeModele; }
    public function setSnapVehiculeModele(?string $v): static { $this->snapVehiculeModele = $v; return $this; }
    public function hasSnapshot(): bool { return $this->snapClientNom !== null; }
}
