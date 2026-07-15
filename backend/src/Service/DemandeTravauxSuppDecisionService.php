<?php

namespace App\Service;

use App\Entity\DemandeTravauxSupp;
use App\Entity\Notification;
use App\Entity\OrdreReparation;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Enregistre la décision du client sur une demande de travaux supplémentaires.
 * Logique partagée entre la page publique tokenisée et l'espace client connecté :
 * trace horodatée (IP/UA), signature obligatoire à l'acceptation, création de
 * l'OR complémentaire figé, et notification du staff (cloche + Mercure).
 * Gère aussi le canal téléphone : accord enregistré par le staff, OR figé non
 * signé, puis confirmation de signature en ligne par le client.
 */
class DemandeTravauxSuppDecisionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrdreReparationPolicy $orPolicy,
        private MercureNotifier $mercureNotifier,
        private NotificationDispatcher $notificationDispatcher,
    ) {}

    /**
     * @param string $canal Canal de la décision (KPI pilote) : 'client_token'
     *                      (page publique tokenisée) / 'client_portail'
     *                      (portail connecté).
     *
     * @return array{error: string, status: int}|array{demande: DemandeTravauxSupp}
     */
    public function decide(DemandeTravauxSupp $demande, ?string $decision, ?string $signatureData, Request $request, string $canal): array
    {
        // Accord déjà donné par téléphone : le POST decision ne sert plus qu'à
        // confirmer la signature en ligne (le canal de décision reste
        // 'staff_telephone' — la décision a eu lieu au téléphone).
        if ($demande->isEnAttenteConfirmationTelephone()) {
            return $this->confirmerSignatureTelephone($demande, $decision, $signatureData, $request);
        }

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
        $demande->setDecisionCanal($canal);

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

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Double-soumission (double-clic / retry réseau) : un OR complémentaire
            // existe déjà pour cette demande (index unique partiel) — la première
            // requête l'emporte, celle-ci est rejetée sans créer de doublon.
            return ['error' => 'Une décision est déjà en cours d\'enregistrement pour cette demande', 'status' => 409];
        }

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
     * Décision prise par téléphone, enregistrée par le staff : les travaux
     * démarrent aussitôt, l'OR complémentaire est créé figé mais NON signé,
     * et le lien de signature part immédiatement au client (email ou SMS).
     *
     * @return array{error: string, status: int}|array{demande: DemandeTravauxSupp, envoye: bool|null, envoi_erreur: string|null, destinataire: string|null}
     */
    public function decideParTelephone(DemandeTravauxSupp $demande, ?string $decision, ?string $commentaire, string $canalEnvoi, User $staff, Request $request): array
    {
        if (in_array($demande->getStatut(), [DemandeTravauxSupp::STATUT_ACCEPTE, DemandeTravauxSupp::STATUT_REFUSE], true)) {
            return ['error' => 'Décision déjà enregistrée pour cette demande', 'status' => 409];
        }

        if (!in_array($decision, [DemandeTravauxSupp::STATUT_ACCEPTE, DemandeTravauxSupp::STATUT_REFUSE], true)) {
            return ['error' => 'Décision invalide (accepte ou refuse)', 'status' => 400];
        }

        if ($decision === DemandeTravauxSupp::STATUT_ACCEPTE
            && $demande->getPrestationsChoisies() === []
            && (float) ($demande->getPrixEstime() ?? '0') <= 0) {
            return ['error' => 'Demande sans prestations chiffrées : complétez-la avant d\'enregistrer un accord téléphonique', 'status' => 409];
        }

        $demande->setDecisionClient($decision);
        $demande->setDecisionClientAt(new \DateTime());
        $demande->setDecisionIp($request->getClientIp());
        $demande->setDecisionUserAgent(mb_substr($request->headers->get('User-Agent', ''), 0, 500));
        $demande->setDecisionCanal(DemandeTravauxSupp::CANAL_STAFF_TELEPHONE);
        $demande->setDecisionEnregistreePar($staff);
        // sentAt intact : il garde la sémantique « demande envoyée pour décision »

        if ($commentaire !== null && trim($commentaire) !== '') {
            $this->appendCommentaireTelephone($demande, $decision, trim($commentaire), $staff);
        }

        if ($decision === DemandeTravauxSupp::STATUT_ACCEPTE) {
            $demande->setStatut(DemandeTravauxSupp::STATUT_ACCEPTE);

            // OR figé mais signable ensuite : 'en_attente_signature' (statut déjà
            // utilisé par le workflow de rectification) bloque l'édition (canEdit
            // exige brouillon) et laisse sign() poser 'signe' à la confirmation.
            // finalize() poserait 'termine' (état final, PDF exposé au client) :
            // incompatible avec une signature ultérieure.
            $or = $this->buildOrComplementaire($demande);
            $or->setStatut('en_attente_signature');
            $demande->setOrComplementaire($or);
        } else {
            $demande->setStatut(DemandeTravauxSupp::STATUT_REFUSE);
        }

        $notif = $this->buildStaffNotificationTelephone($demande, $decision, $staff);
        if ($notif) {
            $this->em->persist($notif);
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Double-soumission (double-clic / retry réseau) : un OR complémentaire
            // existe déjà pour cette demande (index unique partiel) — on rejette
            // sans créer de doublon ni renvoyer un second lien de signature.
            return ['error' => 'Une décision est déjà en cours d\'enregistrement pour cette demande', 'status' => 409];
        }

        if ($notif) {
            try {
                $this->mercureNotifier->publishToAtelier($notif->getAtelierId(), $notif);
            } catch (\Throwable) {
                // Mercure indisponible : la notification reste visible dans la cloche
            }
        }

        if ($decision !== DemandeTravauxSupp::STATUT_ACCEPTE) {
            return ['demande' => $demande, 'envoye' => null, 'envoi_erreur' => null, 'destinataire' => null];
        }

        return array_merge(['demande' => $demande], $this->envoyerLienConfirmation($demande, $canalEnvoi, $request));
    }

    /**
     * Confirmation a posteriori de l'accord téléphonique : seule l'acceptation
     * signée est admise, elle signe l'OR complémentaire déjà figé.
     *
     * @return array{error: string, status: int}|array{demande: DemandeTravauxSupp}
     */
    private function confirmerSignatureTelephone(DemandeTravauxSupp $demande, ?string $decision, ?string $signatureData, Request $request): array
    {
        if ($decision === DemandeTravauxSupp::STATUT_REFUSE) {
            return ['error' => 'Accord déjà enregistré par téléphone — contactez l\'atelier pour toute modification', 'status' => 409];
        }

        if ($decision !== DemandeTravauxSupp::STATUT_ACCEPTE) {
            return ['error' => 'Décision invalide (accepte ou refuse)', 'status' => 400];
        }

        if (!$signatureData || !str_starts_with($signatureData, 'data:image/')) {
            return ['error' => 'Signature requise pour confirmer votre accord', 'status' => 400];
        }

        $demande->setSignatureClient($signatureData);
        $demande->setSignedAt(new \DateTime());

        $or = $demande->getOrComplementaire();
        if ($or) {
            $this->orPolicy->sign($or, $signatureData, $request);
        }

        $notif = $this->buildNotificationSignatureConfirmee($demande);
        if ($notif) {
            $this->em->persist($notif);
        }

        $this->em->flush();

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
        $or = $this->buildOrComplementaire($demande);

        // Sign the OR immediately (client just signed)
        $this->orPolicy->sign($or, $signatureData, $request);

        return $or;
    }

    /**
     * Création commune de l'OR complémentaire (canaux en ligne + téléphone) :
     * numéro dérivé de l'OR initial, lignes depuis les prestations choisies.
     */
    private function buildOrComplementaire(DemandeTravauxSupp $demande): OrdreReparation
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

        return $or;
    }

    /**
     * Commentaire du staff appendé aux notes réceptionniste, préfixé et horodaté
     * pour rester lisible dans le fil des notes.
     */
    private function appendCommentaireTelephone(DemandeTravauxSupp $demande, string $decision, string $commentaire, User $staff): void
    {
        $prefixe = sprintf(
            '[%s tél. %s — %s]',
            $decision === DemandeTravauxSupp::STATUT_ACCEPTE ? 'Accord' : 'Refus',
            (new \DateTime())->format('d/m/Y H:i'),
            $this->nomStaff($staff),
        );

        $notes = $demande->getNotesReceptionniste();
        $demande->setNotesReceptionniste(($notes ? $notes . "\n" : '') . $prefixe . ' ' . $commentaire);
    }

    /**
     * Envoi immédiat du lien de signature. Transactionnel (confirmation légale
     * d'un accord déjà donné) : pas d'interrupteur d'étape ConfigAtelier,
     * contrairement à demande_relance.
     *
     * @return array{envoye: bool, envoi_erreur: string|null, destinataire: string|null}
     */
    private function envoyerLienConfirmation(DemandeTravauxSupp $demande, string $canal, Request $request): array
    {
        $rdv = $demande->getRendezVous();
        $client = $rdv->getClient();
        $vehicule = $rdv->getVehicule();

        $destinataire = $canal === 'sms' ? $client?->getTelephone() : $client?->getEmail();
        if (!$destinataire) {
            return [
                'envoye' => false,
                'envoi_erreur' => 'Aucun ' . ($canal === 'sms' ? 'téléphone' : 'e-mail') . ' renseigné pour ce client',
                'destinataire' => null,
            ];
        }

        $baseUrl = rtrim($_ENV['PUBLIC_URL'] ?? $request->getSchemeAndHttpHost(), '/');

        $result = $this->notificationDispatcher->sendFromTemplate(
            'demande_confirmation_telephone',
            $canal,
            $rdv->getAtelierId() ?? 0,
            $destinataire,
            [
                'client_prenom' => $client?->getPrenom() ?? '',
                'vehicule' => $vehicule
                    ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                    : 'votre moto',
                'lien' => $baseUrl . '/public/demande/' . $demande->getTokenValidation(),
                'prix_ttc' => $demande->getPrixEstime() ?? '',
            ],
            'DemandeTravauxSupp',
            $demande->getId(),
        );

        return [
            'envoye' => $result->isSuccess(),
            'envoi_erreur' => $result->getErrorMessage(),
            'destinataire' => $destinataire,
        ];
    }

    private function nomStaff(User $staff): string
    {
        return trim(($staff->getPrenom() ?? '') . ' ' . ($staff->getNom() ?? '')) ?: $staff->getEmail();
    }

    private function buildStaffNotificationTelephone(DemandeTravauxSupp $demande, string $decision, User $staff): ?Notification
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
        $notif->setSeverity('warning');
        $notif->setTitle($accepted
            ? 'Travaux supp acceptés par téléphone — signature en attente'
            : 'Travaux supp refusés par téléphone');
        $notif->setMessage(sprintf(
            '%s %s a %s les travaux complémentaires par téléphone — enregistré par %s (RDV du %s%s)%s',
            $client?->getPrenom() ?? 'Le',
            $client?->getNom() ?? 'client',
            $accepted ? 'accepté' : 'refusé',
            $this->nomStaff($staff),
            $rdv->getDateRdv()->format('d/m/Y'),
            $demande->getPrixEstime() ? ', ' . $demande->getPrixEstime() . '€ TTC' : '',
            $accepted ? ' — signature en ligne en attente' : '',
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setRelatedEntityId($demande->getId());
        $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN', 'ROLE_MECANICIEN']);
        $notif->setPriority($accepted ? 'high' : 'normal');

        return $notif;
    }

    private function buildNotificationSignatureConfirmee(DemandeTravauxSupp $demande): ?Notification
    {
        $rdv = $demande->getRendezVous();
        $atelierId = $rdv->getAtelierId();
        if (!$atelierId) {
            return null;
        }

        $client = $rdv->getClient();

        $notif = new Notification();
        $notif->setAtelierId($atelierId);
        $notif->setType('demande_decision_client');
        $notif->setSeverity('success');
        $notif->setTitle('Signature en ligne confirmée');
        $notif->setMessage(sprintf(
            '%s %s a confirmé et signé en ligne son accord téléphonique sur les travaux complémentaires (RDV du %s%s)',
            $client?->getPrenom() ?? 'Le',
            $client?->getNom() ?? 'client',
            $rdv->getDateRdv()->format('d/m/Y'),
            $demande->getPrixEstime() ? ', ' . $demande->getPrixEstime() . '€ TTC' : '',
        ));
        $notif->setRelatedEntityType('DemandeTravauxSupp');
        $notif->setRelatedEntityId($demande->getId());
        $notif->setTargetRoles(['ROLE_RECEPTIONNAIRE', 'ROLE_ADMIN', 'ROLE_MECANICIEN']);
        $notif->setPriority('normal');

        return $notif;
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
