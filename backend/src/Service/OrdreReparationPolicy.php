<?php

namespace App\Service;

use App\Entity\OrdreReparation;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

class OrdreReparationPolicy
{
    /**
     * Can this user edit the OR content?
     * brouillon → free edit by receptionniste/responsable
     * en_attente_signature → blocked except cancel
     * signe/execute/termine → frozen, needs rectification
     */
    public function canEdit(OrdreReparation $or, User $user): bool
    {
        // Mécanicien can never edit an OR
        if ($user->getRole() === 'mecanicien') {
            return false;
        }

        return $or->getStatut() === 'brouillon';
    }

    public function canSign(OrdreReparation $or): bool
    {
        return in_array($or->getStatut(), ['brouillon', 'en_attente_signature'], true);
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
            'snap_client_nom' => $or->getSnapClientNom(),
            'snap_client_prenom' => $or->getSnapClientPrenom(),
            'snap_vehicule_plaque' => $or->getSnapVehiculePlaque(),
            'snap_vehicule_marque' => $or->getSnapVehiculeMarque(),
            'snap_vehicule_modele' => $or->getSnapVehiculeModele(),
            'created_at' => $or->getCreatedAt()->format('c'),
        ];
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
     * Sign the OR: build snapshot, compute hash, freeze.
     */
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
