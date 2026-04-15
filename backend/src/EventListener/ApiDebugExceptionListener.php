<?php
namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception')]
class ApiDebugExceptionListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/rendez-vous') && !str_starts_with($path, '/api/ordres-reparation')) {
            return;
        }

        $body = trim((string) $request->getContent());
        if (strlen($body) > 1000) {
            $body = substr($body, 0, 1000) . '...';
        }

        $error = $event->getThrowable();

        $this->logger->error('API workshop exception', [
            'method' => $request->getMethod(),
            'path' => $path,
            'query' => $request->query->all(),
            'body' => $body,
            'error_type' => $error::class,
            'error_message' => $error->getMessage(),
        ]);
    }
}
