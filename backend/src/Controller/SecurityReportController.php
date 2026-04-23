<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint de réception des rapports de violation CSP envoyés par les navigateurs.
 * Public (pas d'auth) car appelé par le navigateur lui-même via report-uri/report-to.
 *
 * En mode Report-Only, les violations sont uniquement loggées, jamais bloquantes.
 * Permet d'identifier ce qu'il faudra autoriser avant de basculer en mode bloquant.
 */
class SecurityReportController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/security/csp-report', methods: ['POST'])]
    public function cspReport(Request $request): Response
    {
        $payload = $request->getContent();
        $decoded = json_decode($payload, true);

        // Anti-DoS minimal : limite la taille du payload loggé.
        $excerpt = is_array($decoded) ? $decoded : ['raw' => substr($payload, 0, 2000)];

        $this->logger->warning('CSP violation reported', [
            'csp' => $excerpt,
            'ua' => $request->headers->get('User-Agent', ''),
            'ip' => $request->getClientIp(),
        ]);

        // Réponse vide attendue par les navigateurs.
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
