<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EssaiRoutier;
use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Formats a RendezVous entity into a flat array suitable for API responses.
 * Centralises all flattening logic to keep controllers thin.
 */
class RendezVousViewService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RapportInterventionService $rapportService,
    ) {}

    public function flatten(RendezVous $r): array
    {
        $client   = $r->getClient();
        $vehicule = $r->getVehicule();
        $pont     = $r->getPont();
        $orInitial = $this->findInitialOrdre($r);
        $essai    = $this->findLatestEssai($r);
        $rapport  = $this->rapportService->findLatestForRdv($r);
        $etatVehiculeReception = $this->decodeJson($r->getEtatVehicule());

        return [
            'id'                      => $r->getId(),
            'date_rdv'                => $r->getDateRdv()->format('Y-m-d'),
            'heure_debut'             => $r->getHeureRdv()->format('H:i'),
            'heure_rdv'               => $r->getHeureRdv()->format('H:i:s'),
            'type_intervention'       => $r->getTypeIntervention(),
            'statut'                  => $r->getStatut(),
            'status'                  => $r->getStatut(),
            'commentaire'             => $r->getCommentaire(),
            'commentaire_client'      => $r->getCommentaire(),
            'description_probleme'    => $r->getCommentaire(),
            'temps_estime'            => $r->getTempsEstime(),
            'temps_effectif_minutes'  => $r->getTempsEffectifMinutes(),
            'heure_debut_travail'     => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_debut_travaux'     => $r->getHeureDebutTravail()?->format('Y-m-d H:i:s'),
            'heure_fin_travail'       => $r->getHeureFinTravail()?->format('Y-m-d H:i:s'),
            'gardiennage_debut_at'    => $r->getGardiennageDebutAt()?->format('Y-m-d H:i:s'),
            'gardiennage_motif'       => $r->getGardiennageMotif(),
            'motif_annulation'        => $r->getMotifAnnulation(),
            'commentaire_annulation'  => $r->getCommentaireAnnulation(),
            'client_nom'              => $client ? ($client->getPrenom() . ' ' . $client->getNom()) : null,
            'client_telephone'        => $client?->getTelephone(),
            'client_email'            => $client?->getEmail(),
            'vehicule_info'           => $vehicule ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? '')) : null,
            'vehicule_plaque'         => $vehicule?->getPlaque(),
            'vehicule_type'           => $vehicule?->getTypeMoto(),
            'km_reception'            => $r->getKilometrage(),
            'etat_vehicule_reception' => $etatVehiculeReception,
            'pont_nom'                => $pont?->getNom(),
            'mecanicien_nom'          => $r->getMecanicien() ? ($r->getMecanicien()->getPrenom() . ' ' . $r->getMecanicien()->getNom()) : null,
            'or_id'                   => $orInitial?->getId(),
            'or_is_signed'            => $orInitial?->isSigned() ?? false,
            'or_mechanic_notes'       => $orInitial?->getMechanicNotes(),
            'or_mechanic_checkup'     => $orInitial?->getMechanicCheckup(),
            'rapport_id'              => $rapport?->getId(),
            'rapport_mecanicien_signe'=> $rapport?->getSignatureMecanicien() ? true : false,
            'rapport_is_signed_both'  => $rapport?->isSignedByBoth() ?? false,
            'essai_routier_id'        => $essai?->getId(),
            'essai_routier_statut'    => $essai?->getStatut(),
            'essai_routier_valide'    => $essai?->isValide() ?? false,
            'token_suivi'             => $r->getTokenSuivi(),
        ];
    }

    public function findInitialOrdre(RendezVous $rdv): ?OrdreReparation
    {
        return $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        ) ?? $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv],
            ['id' => 'DESC'],
        );
    }

    public function findLatestEssai(RendezVous $rdv): ?EssaiRoutier
    {
        return $rdv->getEssaiRoutier()
            ?? $this->em->getRepository(EssaiRoutier::class)->findOneBy(
                ['rendezVous' => $rdv],
                ['id' => 'DESC'],
            );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeJson(?string $payload): ?array
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
