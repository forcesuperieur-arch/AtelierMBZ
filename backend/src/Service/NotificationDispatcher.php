<?php

namespace App\Service;

use App\Entity\NotificationLog;
use App\Entity\NotificationProviderConfig;
use App\Entity\NotificationTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Multi-provider notification dispatcher.
 * Tries providers in priority order with fallback. Logs every attempt.
 */
class NotificationDispatcher
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConfigEncryptionService $encryption,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private NotificationTemplateCatalog $templateCatalog,
    ) {}

    /**
     * Send a notification through the configured providers for the given channel + atelier.
     * Falls back to next provider on failure.
     */
    public function send(NotificationMessage $msg): NotificationResult
    {
        $providers = $this->getActiveProviders($msg->getChannel(), $msg->getAtelierId());

        if (empty($providers)) {
            $this->logger->warning('No active providers for channel {channel} atelier {atelier}', [
                'channel' => $msg->getChannel(),
                'atelier' => $msg->getAtelierId(),
            ]);
            return NotificationResult::allFailed();
        }

        foreach ($providers as $providerConfig) {
            try {
                $result = $this->sendViaProvider($msg, $providerConfig);
                $this->logAttempt($msg, $providerConfig, $result);

                if ($result->isSuccess()) {
                    return $result;
                }
            } catch (\Throwable $e) {
                $result = NotificationResult::fail($providerConfig->getProvider(), $e->getMessage());
                $this->logAttempt($msg, $providerConfig, $result);
                $this->logger->error('Provider {provider} failed: {error}', [
                    'provider' => $providerConfig->getProvider(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return NotificationResult::allFailed();
    }

    /**
     * Send using a template: resolve the template, render it, then dispatch.
     */
    public function sendFromTemplate(
        string $templateCode,
        string $channel,
        int $atelierId,
        string $recipient,
        array $variables,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
    ): NotificationResult {
        $this->templateCatalog->ensureDefaultsForAtelier($atelierId);

        $template = $this->em->getRepository(NotificationTemplate::class)->findOneBy([
            'atelierId' => $atelierId,
            'code' => $templateCode,
            'channel' => $channel,
            'isActive' => true,
        ]);

        if (!$template) {
            $this->logger->warning('Template {code}/{channel} not found for atelier {atelier}', [
                'code' => $templateCode,
                'channel' => $channel,
                'atelier' => $atelierId,
            ]);
            return NotificationResult::fail('template', "Template {$templateCode}/{$channel} not found");
        }

        $body = $template->render($variables);
        $subject = $template->renderSubject($variables);

        $msg = new NotificationMessage(
            $channel,
            $atelierId,
            $recipient,
            $body,
            $subject,
            $templateCode,
            $relatedEntityType,
            $relatedEntityId,
        );

        return $this->send($msg);
    }

    /**
     * Get active providers for a channel+atelier, ordered by: primary first, then priority ASC, then fallback.
     *
     * @return NotificationProviderConfig[]
     */
    private function getActiveProviders(string $channel, int $atelierId): array
    {
        return $this->em->getRepository(NotificationProviderConfig::class)
            ->createQueryBuilder('p')
            ->where('p.channel = :channel')
            ->andWhere('p.atelierId = :atelierId')
            ->andWhere('p.isActive = true')
            ->setParameter('channel', $channel)
            ->setParameter('atelierId', $atelierId)
            ->orderBy('p.isPrimary', 'DESC')
            ->addOrderBy('p.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dispatch to specific provider based on its type.
     */
    private function sendViaProvider(NotificationMessage $msg, NotificationProviderConfig $config): NotificationResult
    {
        $providerName = $config->getProvider();
        $credentials = $this->encryption->decrypt($config->getConfigEncrypted());

        return match ($msg->getChannel()) {
            'sms' => $this->sendSms($msg, $providerName, $credentials),
            'email' => $this->sendEmail($msg, $providerName, $credentials),
            default => NotificationResult::fail($providerName, "Unknown channel: {$msg->getChannel()}"),
        };
    }

    private function sendSms(NotificationMessage $msg, string $provider, array $credentials): NotificationResult
    {
        // Use Symfony Notifier transports
        return match ($provider) {
            'twilio' => $this->sendSmsTwilio($msg, $credentials),
            'ovh' => $this->sendSmsOvh($msg, $credentials),
            'log_sms' => $this->sendSmsLogged($msg),
            default => NotificationResult::fail($provider, "Unknown SMS provider: {$provider}"),
        };
    }

    private function sendSmsLogged(NotificationMessage $msg): NotificationResult
    {
        $messageId = 'sms-log-' . bin2hex(random_bytes(6));

        $this->logger->info('SMS dispatch simulated locally', [
            'recipient' => $msg->getRecipient(),
            'atelierId' => $msg->getAtelierId(),
            'body' => $msg->getBody(),
            'messageId' => $messageId,
        ]);

        return NotificationResult::ok('log_sms', $messageId);
    }

    private function sendSmsTwilio(NotificationMessage $msg, array $credentials): NotificationResult
    {
        $accountSid = $credentials['account_sid'] ?? '';
        $authToken = $credentials['auth_token'] ?? '';
        $from = $credentials['from'] ?? '';

        if (!$accountSid || !$authToken || !$from) {
            return NotificationResult::fail('twilio', 'Missing Twilio credentials');
        }

        // Use Symfony Twilio Notifier transport
        $dsn = "twilio://{$accountSid}:{$authToken}@default?from={$from}";

        try {
            $transport = \Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory::class;
            // Use cURL directly for SMS sending (simpler than wiring full Notifier at runtime)
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
                CURLOPT_POSTFIELDS => http_build_query([
                    'From' => $from,
                    'To' => $msg->getRecipient(),
                    'Body' => $msg->getBody(),
                ]),
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                return NotificationResult::ok('twilio', $data['sid'] ?? null);
            }

            $error = json_decode($response, true);
            return NotificationResult::fail('twilio', $error['message'] ?? "HTTP {$httpCode}");
        } catch (\Throwable $e) {
            return NotificationResult::fail('twilio', $e->getMessage());
        }
    }

    private function sendSmsOvh(NotificationMessage $msg, array $credentials): NotificationResult
    {
        $appKey = $credentials['app_key'] ?? '';
        $appSecret = $credentials['app_secret'] ?? '';
        $consumerKey = $credentials['consumer_key'] ?? '';
        $serviceName = $credentials['service_name'] ?? '';
        $sender = $credentials['sender'] ?? '';

        if (!$appKey || !$appSecret || !$consumerKey || !$serviceName) {
            return NotificationResult::fail('ovh', 'Missing OVH credentials');
        }

        try {
            $url = "https://eu.api.ovh.com/1.0/sms/{$serviceName}/jobs";
            $body = json_encode([
                'message' => $msg->getBody(),
                'receivers' => [$msg->getRecipient()],
                'sender' => $sender ?: $serviceName,
                'noStopClause' => false,
            ]);

            $timestamp = time();
            $toSign = $appSecret . '+' . $consumerKey . '+POST+' . $url . '+' . $body . '+' . $timestamp;
            $signature = '$1$' . sha1($toSign);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    "X-Ovh-Application: {$appKey}",
                    "X-Ovh-Consumer: {$consumerKey}",
                    "X-Ovh-Signature: {$signature}",
                    "X-Ovh-Timestamp: {$timestamp}",
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                $ids = $data['ids'] ?? [];
                return NotificationResult::ok('ovh', $ids[0] ?? null);
            }

            return NotificationResult::fail('ovh', "HTTP {$httpCode}: {$response}");
        } catch (\Throwable $e) {
            return NotificationResult::fail('ovh', $e->getMessage());
        }
    }

    private function sendEmail(NotificationMessage $msg, string $provider, array $credentials): NotificationResult
    {
        try {
            $from = $credentials['from'] ?? $credentials['sender'] ?? 'noreply@atelier-moto.fr';

            $email = (new Email())
                ->from($from)
                ->to($msg->getRecipient())
                ->subject($msg->getSubject() ?? 'Notification Atelier Moto')
                ->html($msg->getBody());

            // For mailgun / smtp_custom, the Symfony Mailer is already configured via DSN
            // The provider-specific DSN can be set at runtime if needed
            $this->mailer->send($email);

            return NotificationResult::ok($provider);
        } catch (\Throwable $e) {
            return NotificationResult::fail($provider, $e->getMessage());
        }
    }

    /**
     * Log every notification attempt to NotificationLog.
     */
    private function logAttempt(NotificationMessage $msg, NotificationProviderConfig $config, NotificationResult $result): void
    {
        $log = new NotificationLog();
        $log->setAtelierId($msg->getAtelierId());
        $log->setChannel($msg->getChannel());
        $log->setProvider($config->getProvider());
        $log->setTemplateCode($msg->getTemplateCode());
        $log->setToRecipient($msg->getRecipient());
        $log->setSubject($msg->getSubject());
        $log->setStatus($result->isSuccess() ? 'sent' : 'failed');
        $log->setProviderMessageId($result->getProviderMessageId());
        $log->setErrorMessage($result->getErrorMessage());
        $log->setRelatedEntityType($msg->getRelatedEntityType());
        $log->setRelatedEntityId($msg->getRelatedEntityId());

        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * Test a provider configuration by sending a test message.
     */
    public function testProvider(NotificationProviderConfig $config, string $testRecipient): NotificationResult
    {
        $msg = new NotificationMessage(
            $config->getChannel(),
            $config->getAtelierId(),
            $testRecipient,
            'Test de connexion — Atelier Moto Pro',
            'Test Provider',
        );

        return $this->sendViaProvider($msg, $config);
    }
}
