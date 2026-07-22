<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mime\Email;

/**
 * Observabilité minimale sans dépendance : envoie un e-mail à l'admin quand une
 * erreur serveur (5xx) survient en HTTP, ou quand un message du worker échoue
 * définitivement. Remplace l'absence de Sentry pour le pilote.
 *
 * - Actif UNIQUEMENT en prod (sinon flood en dev/E2E).
 * - Anti-spam : une même signature d'erreur n'envoie qu'un mail toutes les 15 min.
 * - Best-effort : n'échoue jamais (une alerte cassée ne doit pas casser la réponse).
 */
class ErrorMailerListener implements EventSubscriberInterface
{
    private const THROTTLE_SECONDS = 900;

    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -64],
            WorkerMessageFailedEvent::class => 'onWorkerFailed',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        // Ne remonter que les vraies erreurs serveur : les 4xx (404, 403, 400,
        // 409 métier…) sont du fonctionnement normal, pas des incidents.
        $status = $throwable instanceof HttpExceptionInterface
            ? $throwable->getStatusCode()
            : 500;
        if ($status < 500) {
            return;
        }

        $request = $event->getRequest();
        $context = sprintf('%s %s', $request->getMethod(), $request->getPathInfo());
        $this->notify('Erreur serveur (HTTP ' . $status . ')', $throwable, $context);
    }

    public function onWorkerFailed(WorkerMessageFailedEvent $event): void
    {
        // Seulement l'échec DÉFINITIF (après épuisement des tentatives) —
        // sinon on alerterait à chaque retry.
        if ($event->willRetry()) {
            return;
        }

        $context = 'Worker Messenger : ' . get_class($event->getEnvelope()->getMessage());
        $this->notify('Échec définitif du worker', $event->getThrowable(), $context);
    }

    private function notify(string $title, \Throwable $throwable, string $context): void
    {
        try {
            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev') !== 'prod') {
                return;
            }

            $to = trim((string) ($_ENV['ADMIN_EMAIL'] ?? ''));
            if ($to === '' || $to === 'CHANGEME') {
                return;
            }

            // Anti-spam : une signature (classe + fichier + ligne) = un mail / 15 min.
            $signature = md5($throwable::class . $throwable->getFile() . $throwable->getLine());
            $throttleFile = sys_get_temp_dir() . '/paddock-err-' . $signature;
            if (is_file($throttleFile) && (time() - (int) @filemtime($throttleFile)) < self::THROTTLE_SECONDS) {
                return;
            }
            @touch($throttleFile);

            $from = trim((string) ($_ENV['MAILER_FROM'] ?? '')) ?: 'noreply@paddock.fr';

            $body = sprintf(
                "%s\n\nContexte : %s\n\nException : %s\nMessage : %s\nFichier : %s:%d\n\nTrace :\n%s",
                $title,
                $context,
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
                mb_substr($throwable->getTraceAsString(), 0, 4000),
            );

            $email = (new Email())
                ->from($from)
                ->to($to)
                ->subject('[Paddock] ' . $title)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Ne jamais propager : on se contente de logguer que l'alerte a échoué.
            $this->logger->error('ErrorMailerListener: envoi d\'alerte impossible: {err}', ['err' => $e->getMessage()]);
        }
    }
}
