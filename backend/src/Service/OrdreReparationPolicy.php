<?php

namespace App\Service;

use App\Entity\OrdreReparation;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

class OrdreReparationPolicy
{
    public function __construct(private PdfService $pdfService) {}

    /**
     * Can this user edit the OR content?
     * brouillon → free edit by receptionniste/responsable
     * reception_signee → blocked except mécanicien fields
     * intervention_signee / signe / execute / termine → frozen
     */
    public function canEdit(OrdreReparation $or, User $user): bool
    {
        // Fully frozen after finalization
        if ($or->getStatut() === 'termine') {
            return false;
        }

        // Mécanicien can only edit mechanic fields on reception_signee OR
        if ($user->getRole() === 'mecanicien') {
            return in_array($or->getStatut(), ['brouillon', 'reception_signee'], true);
        }

        return $or->getStatut() === 'brouillon';
    }

    // ─── Réception signatures (PDA) ───

    public function canSignReception(OrdreReparation $or): bool
    {
        return $or->getStatut() === 'brouillon';
    }

    public function signReception(OrdreReparation $or, string $signatureClient, string $signatureAtelier, Request $request): string
    {
        $or->snapshotFromRdv();

        $snapshot = $this->buildSnapshot($or);
        $hash = $this->computeHash($snapshot);

        $or->setSignatureClient($signatureClient);
        $or->setSignatureAtelierReception($signatureAtelier);
        $or->setSignedSnapshot($snapshot);
        $or->setSignedHash($hash);
        $or->setSignedAt(new \DateTime());
        $or->setSignedIp($request->getClientIp());
        $or->setSignedUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 500));
        $or->setSigneReceptionnisteAt(new \DateTime());
        $or->setStatut('reception_signee');

        return $hash;
    }

    // ─── Mécanicien signature ───

    public function canSignMecanicien(OrdreReparation $or): bool
    {
        return $or->getStatut() === 'reception_signee';
    }

    public function signMecanicien(OrdreReparation $or, string $signatureData, int $mecanicienId): void
    {
        $or->setSignatureMecanicien($signatureData);
        $or->setSigneMecanicienAt(new \DateTime());
        $or->setSigneMecanicienId($mecanicienId);
        $or->setStatut('intervention_signee');
    }

    // ─── Client restitution signature ───

    public function canSignRestitution(OrdreReparation $or): bool
    {
        return $or->getStatut() === 'intervention_signee';
    }

    public function signRestitution(OrdreReparation $or, string $signatureData): void
    {
        $or->setSignatureClientRestitution($signatureData);
        $or->setSigneClientRestitutionAt(new \DateTime());
        $or->setStatut('signe');
    }

    // ─── Legacy / compat ───

    public function canSign(OrdreReparation $or): bool
    {
        return $this->canSignReception($or);
    }

    public function sign(OrdreReparation $or, string $signatureData, Request $request): string
    {
        $or->snapshotFromRdv();

        $snapshot = $this->buildSnapshot($or);
        $hash = $this->computeHash($snapshot);

        $or->setSignatureClient($signatureData);
        $or->setSignedSnapshot($snapshot);
        $or->setSignedHash($hash);
        $or->setSignedAt(new \DateTime());
        $or->setSignedIp($request->getClientIp());
        $or->setSignedUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 500));
        $or->setStatut('signe');

        return $hash;
    }

    public function canRectify(OrdreReparation $or, User $user): bool
    {
        if (!$or->isSigned()) {
            return false;
        }

        // Only responsable atelier/magasin or admin can rectify
        $allowedRoles = ['admin', 'super_admin', 'responsable_atelier', 'responsable_magasin'];
        if (!in_array($user->getRole(), $allowedRoles, true)
            && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }

        return true;
    }

    public function canAddComplementaire(OrdreReparation $or, User $user): bool
    {
        // Mécanicien can request additional work on signed/in-progress OR
        return in_array($or->getStatut(), ['signe', 'execute'], true);
    }

    /**
     * Build a complete snapshot of the OR content for signature.
     */
    public function buildSnapshot(OrdreReparation $or): array
    {
        return [
            'id' => $or->getId(),
            'numero_or' => $or->getNumeroOr(),
            'type_or' => $or->getTypeOr(),
            'kilometrage' => $or->getKilometrage(),
            'etat_vehicule' => $or->getEtatVehicule(),
            'travaux' => $or->getTravaux(),
            'travaux_realises' => $or->getTravauxRealises(),
            'alertes' => $or->getAlertes(),
            'recommandations' => $or->getRecommandations(),
            'garantie' => $or->getGarantie(),
            'snap_client_nom' => $or->getSnapClientNom(),
            'snap_client_prenom' => $or->getSnapClientPrenom(),
            'snap_vehicule_plaque' => $or->getSnapVehiculePlaque(),
            'snap_vehicule_marque' => $or->getSnapVehiculeMarque(),
            'snap_vehicule_modele' => $or->getSnapVehiculeModele(),
            'created_at' => $or->getCreatedAt()->format('c'),
        ];
    }

    /**
     * Build a final snapshot when the OR is frozen at RDV completion.
     */
    public function buildFinalSnapshot(OrdreReparation $or): array
    {
        $rdv = $or->getRendezVous();
        return [
            'id' => $or->getId(),
            'numero_or' => $or->getNumeroOr(),
            'type_or' => $or->getTypeOr(),
            'statut' => $or->getStatut(),
            'kilometrage' => $or->getKilometrage(),
            'kilometrage_restitution' => $or->getKilometrageRestitution(),
            'etat_vehicule' => $or->getEtatVehicule(),
            'travaux' => $or->getTravaux(),
            'travaux_realises' => $or->getTravauxRealises(),
            'alertes' => $or->getAlertes(),
            'recommandations' => $or->getRecommandations(),
            'garantie' => $or->getGarantie(),
            'mechanic_notes' => $or->getMechanicNotes(),
            'snap_client_nom' => $or->getSnapClientNom(),
            'snap_client_prenom' => $or->getSnapClientPrenom(),
            'snap_vehicule_plaque' => $or->getSnapVehiculePlaque(),
            'snap_vehicule_marque' => $or->getSnapVehiculeMarque(),
            'snap_vehicule_modele' => $or->getSnapVehiculeModele(),
            'has_signature_client' => $or->getSignatureClient() !== null,
            'has_signature_atelier' => $or->getSignatureAtelierReception() !== null,
            'has_signature_mecanicien' => $or->getSignatureMecanicien() !== null,
            'has_signature_restitution' => $or->getSignatureClientRestitution() !== null,
            'signe_receptionniste_at' => $or->getSigneReceptionnisteAt()?->format('c'),
            'signe_mecanicien_at' => $or->getSigneMecanicienAt()?->format('c'),
            'signe_client_restitution_at' => $or->getSigneClientRestitutionAt()?->format('c'),
            'rdv_date' => $rdv->getDateRdv()->format('Y-m-d'),
            'rdv_heure' => $rdv->getHeureRdv()->format('H:i'),
            'rdv_type_intervention' => $rdv->getTypeIntervention(),
            'created_at' => $or->getCreatedAt()->format('c'),
            'finalized_at' => (new \DateTime())->format('c'),
        ];
    }

    /**
     * Finalize the OR when the RDV is completed.
     * Computes final snapshot + hash, generates the PDF, and freezes the OR.
     */
    public function finalize(OrdreReparation $or): void
    {
        $snapshot = $this->buildFinalSnapshot($or);
        $hash = $this->computeHash($snapshot);

        $or->setSignedSnapshot($snapshot);
        $or->setSignedHash($hash);
        $or->setSignedAt(new \DateTime());
        $or->setStatut('termine');

        // Generate the physical PDF — it will be stored in var/pdf/
        $this->pdfService->generateOrPdf($or);
    }

    /**
     * Compute SHA-256 hash of canonical JSON.
     */
    public function computeHash(array $snapshot): string
    {
        ksort($snapshot);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }

    /**
     * Verify that the stored snapshot hasn't been tampered with.
     */
    public function verifyIntegrity(OrdreReparation $or): bool
    {
        $snapshot = $or->getSignedSnapshot();
        $storedHash = $or->getSignedHash();

        if ($snapshot === null || $storedHash === null) {
            return false;
        }

        return $this->computeHash($snapshot) === $storedHash;
    }

    /**
     * Create a rectified copy of a signed OR.
     */
    public function rectify(OrdreReparation $original, User $user, string $motif): OrdreReparation
    {
        $rectified = new OrdreReparation();
        $rectified->setRendezVous($original->getRendezVous());
        $rectified->setNumeroOr($original->getNumeroOr() . '-R');
        $rectified->setTypeOr($original->getTypeOr());
        $rectified->setKilometrage($original->getKilometrage());
        $rectified->setEtatVehicule($original->getEtatVehicule());
        $rectified->setTravaux($original->getTravaux());
        $rectified->setTravauxRealises($original->getTravauxRealises());
        $rectified->setAlertes($original->getAlertes());
        $rectified->setRecommandations($original->getRecommandations());
        $rectified->setGarantie($original->getGarantie());
        $rectified->setSnapClientNom($original->getSnapClientNom());
        $rectified->setSnapClientPrenom($original->getSnapClientPrenom());
        $rectified->setSnapVehiculePlaque($original->getSnapVehiculePlaque());
        $rectified->setSnapVehiculeMarque($original->getSnapVehiculeMarque());
        $rectified->setSnapVehiculeModele($original->getSnapVehiculeModele());
        $rectified->setRectifiedFrom($original);
        $rectified->setMotifRectification($motif);
        $rectified->setRectifiedBy($user->getId());
        $rectified->setRectifiedAt(new \DateTime());
        $rectified->setStatut('en_attente_signature');

        // Mark original as rectified
        $original->setStatut('rectifie');

        return $rectified;
    }
}
