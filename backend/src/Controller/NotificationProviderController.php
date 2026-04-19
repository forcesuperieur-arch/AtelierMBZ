<?php

namespace App\Controller;

use App\Entity\NotificationLog;
use App\Entity\NotificationProviderConfig;
use App\Service\ConfigEncryptionService;
use App\Service\CurrentAtelierResolver;
use App\Service\NotificationDispatcher;
use App\Service\NotificationProviderConfigSanitizer;
use App\Service\NotificationTemplateCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationProviderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConfigEncryptionService $encryption,
        private NotificationDispatcher $dispatcher,
        private CurrentAtelierResolver $atelierResolver,
        private NotificationProviderConfigSanitizer $configSanitizer,
        private NotificationTemplateCatalog $templateCatalog,
    ) {}

    // ─── Admin: Provider CRUD ───

    /**
     * GET /api/admin/notification-providers
     */
    #[Route('/api/admin/notification-providers', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listProviders(): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json([]);
        }

        $qb = $this->em->getRepository(NotificationProviderConfig::class)->createQueryBuilder('p')
            ->where('p.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('p.channel', 'ASC')
            ->addOrderBy('p.priority', 'ASC');

        $configs = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(NotificationProviderConfig $c) => [
            'id' => $c->getId(),
            'atelierId' => $c->getAtelierId(),
            'channel' => $c->getChannel(),
            'provider' => $c->getProvider(),
            'isPrimary' => $c->isPrimary(),
            'isFallback' => $c->isFallback(),
            'priority' => $c->getPriority(),
            'isActive' => $c->isActive(),
            'lastTestAt' => $c->getLastTestAt()?->format('c'),
            'lastTestSuccess' => $c->getLastTestSuccess(),
            'hasConfig' => $c->getConfigEncrypted() !== '',
        ], $configs));
    }

    /**
     * POST /api/admin/notification-providers
     */
    #[Route('/api/admin/notification-providers', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createProvider(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $atelierId = $this->atelierResolver->resolveAtelierId();

        if (!$atelierId) {
            return $this->json(['error' => 'Atelier actif requis'], 400);
        }

        $channel = $data['channel'] ?? '';
        $provider = $data['provider'] ?? '';

        if (!in_array($channel, ['sms', 'email'], true)) {
            return $this->json(['error' => 'Channel invalide (sms|email)'], 400);
        }
        if (!in_array($provider, ['twilio', 'ovh', 'log_sms', 'mailgun', 'smtp_custom'], true)) {
            return $this->json(['error' => 'Provider invalide'], 400);
        }

        // Check uniqueness
        $existing = $this->em->getRepository(NotificationProviderConfig::class)->findOneBy([
            'atelierId' => $atelierId,
            'channel' => $channel,
            'provider' => $provider,
        ]);
        if ($existing) {
            return $this->json(['error' => 'Ce provider existe déjà pour ce canal'], 409);
        }

        $config = new NotificationProviderConfig();
        $config->setAtelierId($atelierId);
        $config->setChannel($channel);
        $config->setProvider($provider);
        $config->setIsPrimary($data['isPrimary'] ?? false);
        $config->setIsFallback($data['isFallback'] ?? false);
        $config->setPriority($data['priority'] ?? 1);
        $config->setIsActive($data['isActive'] ?? true);

        if (isset($data['config']) && is_array($data['config'])) {
            $config->setConfigEncrypted($this->encryption->encrypt($data['config']));
        }

        $this->em->persist($config);
        $this->em->flush();

        return $this->json(['id' => $config->getId(), 'created' => true], 201);
    }

    /**
     * PUT /api/admin/notification-providers/{id}
     */
    #[Route('/api/admin/notification-providers/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateProvider(int $id, Request $request): JsonResponse
    {
        $config = $this->findScopedProvider($id);
        if (!$config) {
            return $this->json(['error' => 'Provider introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['isPrimary'])) $config->setIsPrimary($data['isPrimary']);
        if (isset($data['isFallback'])) $config->setIsFallback($data['isFallback']);
        if (isset($data['priority'])) $config->setPriority($data['priority']);
        if (isset($data['isActive'])) $config->setIsActive($data['isActive']);

        if (isset($data['config']) && is_array($data['config'])) {
            $existingConfig = $config->getConfigEncrypted() !== ''
                ? $this->encryption->decrypt($config->getConfigEncrypted())
                : [];

            $mergedConfig = $this->configSanitizer->merge($existingConfig, $data['config']);
            if ($mergedConfig !== []) {
                $config->setConfigEncrypted($this->encryption->encrypt($mergedConfig));
            }
        }

        $config->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json(['id' => $config->getId(), 'updated' => true]);
    }

    /**
     * DELETE /api/admin/notification-providers/{id}
     */
    #[Route('/api/admin/notification-providers/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteProvider(int $id): JsonResponse
    {
        $config = $this->findScopedProvider($id);
        if (!$config) {
            return $this->json(['error' => 'Provider introuvable'], 404);
        }

        $this->em->remove($config);
        $this->em->flush();

        return $this->json(['deleted' => true]);
    }

    /**
     * POST /api/admin/notification-providers/{id}/test
     */
    #[Route('/api/admin/notification-providers/{id}/test', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function testProvider(int $id, Request $request): JsonResponse
    {
        $config = $this->findScopedProvider($id);
        if (!$config) {
            return $this->json(['error' => 'Provider introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $testRecipient = $data['recipient'] ?? '';
        if (!$testRecipient) {
            return $this->json(['error' => 'recipient requis pour le test'], 400);
        }

        $result = $this->dispatcher->testProvider($config, $testRecipient);

        $config->setLastTestAt(new \DateTime());
        $config->setLastTestSuccess($result->isSuccess());
        $this->em->flush();

        return $this->json([
            'success' => $result->isSuccess(),
            'provider' => $result->getProvider(),
            'messageId' => $result->getProviderMessageId(),
            'error' => $result->getErrorMessage(),
        ]);
    }

    // ─── Notification Logs ───

    /**
     * GET /api/admin/notification-logs
     */
    #[Route('/api/admin/notification-logs', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listLogs(Request $request): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json([
                'items' => [],
                'page' => 1,
                'limit' => 50,
            ]);
        }

        $qb = $this->em->getRepository(NotificationLog::class)->createQueryBuilder('l')
            ->where('l.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('l.sentAt', 'DESC');
        $channel = $request->query->get('channel');
        if ($channel) {
            $qb->andWhere('l.channel = :channel')->setParameter('channel', $channel);
        }

        $status = $request->query->get('status');
        if ($status) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        $limit = min((int)($request->query->get('limit', 50)), 200);
        $page = max((int)($request->query->get('page', 1)), 1);
        $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);

        $logs = $qb->getQuery()->getResult();

        return $this->json([
            'items' => array_map(fn(NotificationLog $l) => [
                'id' => $l->getId(),
                'channel' => $l->getChannel(),
                'provider' => $l->getProvider(),
                'templateCode' => $l->getTemplateCode(),
                'toRecipient' => $l->getToRecipient(),
                'subject' => $l->getSubject(),
                'status' => $l->getStatus(),
                'errorMessage' => $l->getErrorMessage(),
                'sentAt' => $l->getSentAt()->format('c'),
                'deliveredAt' => $l->getDeliveredAt()?->format('c'),
            ], $logs),
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    // ─── Notification Templates ───

    /**
     * GET /api/admin/notification-templates
     */
    #[Route('/api/admin/notification-templates', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listTemplates(): JsonResponse
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json([]);
        }

        $this->templateCatalog->ensureDefaultsForAtelier($atelierId);

        $qb = $this->em->getRepository(\App\Entity\NotificationTemplate::class)->createQueryBuilder('t')
            ->where('t.atelierId = :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->orderBy('t.code', 'ASC')
            ->addOrderBy('t.channel', 'ASC');
        $templates = $qb->getQuery()->getResult();

        return $this->json(array_map(fn(\App\Entity\NotificationTemplate $t) => [
            'id' => $t->getId(),
            'code' => $t->getCode(),
            'channel' => $t->getChannel(),
            'libelle' => $t->getLibelle(),
            'sujet' => $t->getSujet(),
            'corps' => $t->getCorps(),
            'variables' => $t->getVariables(),
            'isActive' => $t->isActive(),
        ], $templates));
    }

    // ─── Webhooks (public, secured by HMAC) ───

    /**
     * POST /api/webhooks/notifications/{provider}
     * Webhook endpoint for delivery status updates from providers.
     */
    #[Route('/api/webhooks/notifications/{provider}', methods: ['POST'])]
    public function webhookRetour(string $provider, Request $request): JsonResponse
    {
        if (!in_array($provider, ['twilio', 'ovh', 'mailgun'], true)) {
            return $this->json(['error' => 'Unknown provider'], 400);
        }

        $payload = $request->getContent();

        return match ($provider) {
            'twilio' => $this->handleTwilioWebhook($request),
            'ovh' => $this->handleOvhWebhook($request),
            'mailgun' => $this->handleMailgunWebhook($request, $payload),
            default => $this->json(['error' => 'Unsupported'], 400),
        };
    }

    private function findScopedProvider(int $id): ?NotificationProviderConfig
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return null;
        }

        return $this->em->getRepository(NotificationProviderConfig::class)->findOneBy([
            'id' => $id,
            'atelierId' => $atelierId,
        ]);
    }

    private function handleTwilioWebhook(Request $request): JsonResponse
    {
        $messageSid = $request->request->get('MessageSid', '');
        $messageStatus = $request->request->get('MessageStatus', '');

        if (!$messageSid) {
            return $this->json(['error' => 'Missing MessageSid'], 400);
        }

        $statusMap = [
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            'undelivered' => 'failed',
        ];

        $log = $this->em->getRepository(NotificationLog::class)->findOneBy([
            'providerMessageId' => $messageSid,
        ]);

        if ($log && isset($statusMap[$messageStatus])) {
            $log->setStatus($statusMap[$messageStatus]);
            if ($messageStatus === 'delivered') {
                $log->setDeliveredAt(new \DateTime());
            }
            $this->em->flush();
        }

        return $this->json(['received' => true]);
    }

    private function handleOvhWebhook(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        // OVH sends delivery receipts with job IDs
        $jobId = (string)($data['id'] ?? '');
        $status = $data['deliveryReceipt'] ?? null;

        if ($jobId) {
            $log = $this->em->getRepository(NotificationLog::class)->findOneBy([
                'providerMessageId' => $jobId,
            ]);
            if ($log) {
                $log->setStatus($status === 1 ? 'delivered' : 'failed');
                if ($status === 1) {
                    $log->setDeliveredAt(new \DateTime());
                }
                $this->em->flush();
            }
        }

        return $this->json(['received' => true]);
    }

    private function handleMailgunWebhook(Request $request, string $payload): JsonResponse
    {
        // Verify Mailgun webhook signature
        $signature = $request->request->all('signature') ?: [];
        $eventData = $request->request->all('event-data') ?: [];

        if (empty($eventData)) {
            $decoded = json_decode($payload, true) ?? [];
            $signature = $decoded['signature'] ?? [];
            $eventData = $decoded['event-data'] ?? [];
        }

        $messageId = $eventData['message']['headers']['message-id'] ?? '';
        $event = $eventData['event'] ?? '';

        $statusMap = [
            'delivered' => 'delivered',
            'failed' => 'failed',
            'rejected' => 'bounced',
            'complained' => 'bounced',
            'opened' => 'delivered',
        ];

        if ($messageId && isset($statusMap[$event])) {
            $log = $this->em->getRepository(NotificationLog::class)->findOneBy([
                'providerMessageId' => $messageId,
            ]);
            if ($log) {
                $log->setStatus($statusMap[$event]);
                if ($event === 'delivered') {
                    $log->setDeliveredAt(new \DateTime());
                }
                if ($event === 'opened') {
                    $log->setReadAt(new \DateTime());
                }
                $this->em->flush();
            }
        }

        return $this->json(['received' => true]);
    }
}
