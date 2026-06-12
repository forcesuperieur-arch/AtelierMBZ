<?php

namespace App\Service;

use App\Entity\DemandeTravauxSupp;
use App\Entity\Notification;
use App\Entity\OrdreReparation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enregistre la décision du client sur une demande de travaux supplémentaires.
 * Logique partagée entre la page publique tokenisée et l'espace client connecté :
 * trace horodatée (IP/UA), signature obligatoire à l'acceptation, création de
 * l'OR complémentaire figé, et notification du staff (cloche + Mercure).
 */
class DemandeTravauxSuppDecisionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrdreReparationPolicy $orPolicy,
        private MercureNotifier $mercureNotifier,
    ) {}

    /**
     * @return array{error: string, status: int}|array{demande: DemandeTravauxSupp}
     */
    public function decide(DemandeTravauxSupp $demande, ?string $decision, ?string $signatureData, Request $request): array
    {
        if ($demande->getStatut() !== DemandeTravauxSupp::STATUT_EN_ATTENTE_DECISION_CLIENT) {
            return ['error' => 'Décision déjà prise ou demande non envoyée', 'status' => 409];
        }

        if (!in_array($decision, [DemandeTravauxSupp::STATUT_ACCEPTE, DemandeTravauxSupp::STATUT_REFUSE], true)) {
            return ['error' => 'Décision invalide (accepte ou refuse)', 'status' => 400];
        }

        $demande->setDecisionClient($decision);
        $demande->setDecisionClientAt(new \DateTime());
        $demande->setDecisionIp($request->getClientIp());
        $demande->setDecisionUserAgent(mb_substr($request->headers->get('User-Agent', ''), 0, 500));

        if ($decision === DemandeTravauxSupp::STATUT_ACCEPTE) {
            // Signature required for acceptance
            if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
                return ['error' => 'Signature requise pour accepter', 'status' => 400];
            }
            $demande->setSignatureClient($signatureData);
            $demande->setSignedAt(new \DateTime());
            $demande->setStatut(DemandeTravauxSupp::STATUT_ACCEPTE);

            // 4.4 — Auto-create OR complémentaire
            $or = $this->createOrComplementaire($demande, $signatureData, $request);
            $demande->setOrComplementaire($or);
        } else {
            $demande->setStatut(DemandeTravauxSupp::STATUT_REFUSE);
        }

        $notif = $this->buildStaffNotification($demande, $decision);
        if ($notif) {
            $this->em->persist($notif);
        }

        $this->em->flush();

        // Publication après flush : le payload Mercure embarque l'id de la notification
        if ($notif) {
            try {
                $this->mercureNotifier->publishToAtelier($notif->getAtelierId(), $notif);
            } catch (\Throwable) {
                // Mercure indisponible : la notification reste visible dans la cloche
            }
        }

        return ['demande' => $demande];
    }

    /**
     * 4.4 — Create a signed OR complémentaire from the accepted demande.
     */
    private function createOrComplementaire(DemandeTravauxSupp $demande, string $signatureData, Request $request): OrdreReparation
    {
        $rdv = $demande->getRendezVous();

        // Find the initial OR
        $orInitial = $this->em->getRepository(OrdreReparation::class)->findOneBy(
            ['rendezVous' => $rdv, 'typeOr' => 'initial'],
            ['id' => 'DESC'],
        );

        // Build travaux description from prestations choisies
        $travauxLines = array_map(
            fn(array $p) => sprintf('%s — %s€ TTC (%d min)', $p['designation'], $p['prix_ttc'], $p['temps_minutes']),
            $demande->getPrestationsChoisies(),
        );

        $or = new OrdreReparation();
        $or->setRendezVous($rdv);
        $or->setNumeroOr(($orInitial ? $orInitial->getNumeroOr() : 'OR-' . $rdv->getId() . '-' . date('Ymd')) . '-C' . $demande->getId());
        $or->setTypeOr('complementaire');
        $or->setTravaux(implode("\n", $travauxLines));
        $or->setDemandeTravauxSupp($demande);
        $or->setKilometrage($orInitial?->getKilometrage());
        $or->snapshotFromRdv();

        $this->em->persist($or);

        // Sign the OR immediately (client just signed)
        $this->orPolicy->sign($or, $signatureData, $request);

        return $or;
    }

    /**
     * Le staff doit savoir SANS DÉLAI que le client a tranché : c'est ce qui
     * débloque (ou non) la suite des travaux sur le pont.
     */
    private function buildStaffNotification(DemandeTravauxSupp $demande, string $decision): ?Notification
    {
        $rdv = $demande->getRendezVous();
        $atelierId = $rdv->getAtelierId();
        if (!$atelierId) {
            return null;
        }

        $client = $rdv->getClient();
        $accepted = $decision === DemandeTravauxSupp::STATUT_ACCEPTE;

        $notif = new Notification();
        $notif->setAtelierId($atelierId);
        $notif->setType('demande_decision_client');
        $notif->setSeverity($accepted ? 'success' : 'warning');
        $notif->setTitle($accepted ? 'Travaux supplémentaires ACCEPTÉS' : 'Travaux supplémentaires refusés');
        $notif->setMessage(sprintf(
            '%s %s a %s les travaux complémentaires (RDV du %s%s)',
            $client?->getPrenom() ?? 'Le',
            $client?->getNom() ?? 'client',
            $accepted ? 'accepté et signé' : 'refusé',
            $rdv->getDateRdv()->format('d/m/Y'),
            $demande->getPrixEstime() ? ', ' . $demande->getPrixEstime() . '€ TTC' : '',
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setRelatedEntityId($demande->getId());
        $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN', 'ROLE_MECANICIEN']);
        $notif->setPriority($accepted ? 'high' : 'normal');

        return $notif;
    }
}
