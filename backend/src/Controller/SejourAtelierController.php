<?php

namespace App\Controller;

use App\Entity\NotificationLog;
use App\Entity\RendezVous;
use App\Service\NotificationDispatcher;
use App\Service\SejourAtelierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Suivi des motos présentes à l'atelier : onglet « En atelier », bandeau d'alerte
 * au-delà du seuil d'heures ouvrées, et relance du client depuis cet écran.
 * Le TenantFilter restreint automatiquement les RDV à l'atelier de l'utilisateur.
 */
#[Route('/api/sejour-atelier')]
#[IsGranted('ROLE_USER')]
class SejourAtelierController extends AbstractController
{
    private const TEMPLATE_RELANCE = 'sejour_prolonge';

    public function __construct(
        private SejourAtelierService $sejour,
        private EntityManagerInterface $em,
        private NotificationDispatcher $dispatcher,
    ) {}

    /** Seules les motos au-delà du seuil (bandeau d'alerte planning/dashboard). */
    #[Route('/alertes', methods: ['GET'])]
    public function alertes(Request $request): JsonResponse
    {
        $seuil = $this->seuilDemande($request);
        $motos = $this->sejour->motosEnDepassement($seuil);

        return $this->json([
            'seuil_heures' => $seuil ?? $this->sejour->seuilPourAtelier($this->atelierCourant()),
            'total' => count($motos),
            'motos' => $motos,
        ]);
    }

    /**
     * Toutes les motos présentes à l'atelier (onglet de suivi), avec le drapeau
     * de dépassement, la dernière relance envoyée et un récapitulatif par statut.
     */
    #[Route('/motos', methods: ['GET'])]
    public function motos(Request $request): JsonResponse
    {
        $seuil = $this->seuilDemande($request);
        $motos = $this->sejour->motosEnAtelier($seuil);
        $seuilEffectif = $seuil ?? $this->sejour->seuilPourAtelier($this->atelierCourant());

        $relances = $this->dernieresRelances(array_map(static fn (array $m) => $m['rdv_id'], $motos));
        $parStatut = [];
        foreach ($motos as $index => $moto) {
            $motos[$index]['derniere_relance'] = $relances[$moto['rdv_id']] ?? null;
            $parStatut[$moto['statut']] = ($parStatut[$moto['statut']] ?? 0) + 1;
        }

        $depassements = array_filter($motos, static fn (array $m) => $m['en_depassement']);
        $heures = array_map(static fn (array $m) => $m['heures_ouvrees'], $motos);

        return $this->json([
            'seuil_heures' => $seuilEffectif,
            'alerte_active' => $this->sejour->alerteActivePourAtelier($this->atelierCourant()),
            'total' => count($motos),
            'total_depassement' => count($depassements),
            'heures_ouvrees_moyenne' => $motos ? round(array_sum($heures) / count($heures), 1) : 0,
            'heures_ouvrees_max' => $motos ? max($heures) : 0,
            'par_statut' => $parStatut,
            'motos' => $motos,
        ]);
    }

    /**
     * Relance le client d'une moto qui traîne (e-mail si connu, sinon SMS).
     * Le message libre du staff est inséré dans le template `sejour_prolonge`.
     */
    #[Route('/{id}/relancer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function relancer(int $id, Request $request): JsonResponse
    {
        $rdv = $this->em->getRepository(RendezVous::class)->find($id);
        if (!$rdv) {
            return $this->json(['error' => 'RDV_NOT_FOUND'], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($rdv->getStatut(), SejourAtelierService::STATUTS_EN_ATELIER, true)) {
            return $this->json(['error' => 'MOTO_PAS_EN_ATELIER'], Response::HTTP_CONFLICT);
        }

        $client = $rdv->getClient();
        if (!$client) {
            return $this->json(['error' => 'CLIENT_INCONNU'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            $message = "Nous revenons vers vous au sujet de l'intervention en cours.";
        }
        if (mb_strlen($message) > 500) {
            return $this->json(['error' => 'MESSAGE_TROP_LONG'], Response::HTTP_BAD_REQUEST);
        }

        $canalDemande = $payload['canal'] ?? null;
        $email = $client->getEmail();
        $telephone = $client->getTelephone();
        $canal = match (true) {
            $canalDemande === 'sms' && $telephone => 'sms',
            $canalDemande === 'email' && $email => 'email',
            $canalDemande !== null => null,
            (bool) $email => 'email',
            (bool) $telephone => 'sms',
            default => null,
        };

        if ($canal === null) {
            return $this->json(['error' => 'AUCUN_CANAL_DISPONIBLE'], Response::HTTP_CONFLICT);
        }

        $destinataire = $canal === 'email' ? $email : $telephone;
        $vehicule = $rdv->getVehicule();
        $recuLe = $this->sejour->dateArriveeAtelier($rdv);
        $baseUrl = rtrim($_ENV['PUBLIC_URL'] ?? $request->getSchemeAndHttpHost(), '/');

        $result = $this->dispatcher->sendFromTemplate(
            self::TEMPLATE_RELANCE,
            $canal,
            $rdv->getAtelierId() ?? 0,
            (string) $destinataire,
            [
                'client_prenom' => $client->getPrenom(),
                'vehicule' => $vehicule
                    ? trim(($vehicule->getMarque() ?? '') . ' ' . ($vehicule->getModele() ?? ''))
                    : 'moto',
                'plaque' => $vehicule?->getPlaque() ?? '',
                'recu_le' => $recuLe->format('d/m/Y'),
                'message_atelier' => $message,
                'suivi_url' => $baseUrl . '/public/suivi?token=' . $rdv->getTokenSuivi(),
                'atelier_nom' => '',
            ],
            'RendezVous',
            $rdv->getId(),
        );

        if (!$result->isSuccess()) {
            return $this->json([
                'error' => 'ENVOI_ECHOUE',
                'canal' => $canal,
                'detail' => $result->getErrorMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'envoye' => true,
            'canal' => $canal,
            'destinataire' => $destinataire,
        ]);
    }

    /**
     * Date du dernier envoi de relance par RDV, lue dans le journal des notifications.
     *
     * @param list<int> $rdvIds
     *
     * @return array<int, string>
     */
    private function dernieresRelances(array $rdvIds): array
    {
        if (!$rdvIds) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('l.relatedEntityId AS rdvId', 'MAX(l.sentAt) AS derniere')
            ->from(NotificationLog::class, 'l')
            ->where('l.templateCode = :code')
            ->andWhere('l.relatedEntityType = :entite')
            ->andWhere('l.relatedEntityId IN (:ids)')
            ->andWhere('l.status != :echec')
            ->setParameter('code', self::TEMPLATE_RELANCE)
            ->setParameter('entite', 'RendezVous')
            ->setParameter('ids', $rdvIds)
            ->setParameter('echec', 'failed')
            ->groupBy('l.relatedEntityId')
            ->getQuery()
            ->getArrayResult();

        $relances = [];
        foreach ($rows as $row) {
            $date = $row['derniere'];
            // MAX() renvoie une chaîne SQL brute : on normalise en ISO-8601 pour que
            // le front la parse sans ambiguïté.
            if (!$date instanceof \DateTimeInterface) {
                $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $date) ?: null;
            }
            if ($date === null) {
                continue;
            }
            $relances[(int) $row['rdvId']] = $date->format(\DateTimeInterface::ATOM);
        }

        return $relances;
    }

    /**
     * Seuil imposé par l'appelant, ou null pour laisser chaque atelier appliquer
     * le seuil réglé en back-office.
     */
    private function seuilDemande(Request $request): ?int
    {
        $brut = $request->query->get('seuil');
        if ($brut === null || $brut === '') {
            return null;
        }

        return max(1, min((int) $brut, 24 * 365));
    }

    /** Atelier de l'utilisateur connecté (contexte du TenantFilter). */
    private function atelierCourant(): ?int
    {
        $user = $this->getUser();

        return method_exists($user, 'getAtelierId') ? $user->getAtelierId() : null;
    }
}
