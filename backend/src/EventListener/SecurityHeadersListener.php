<?php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-XSS-Protection', '1; mode=block');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');

        // HSTS : uniquement sur connexions HTTPS pour éviter d'épingler le HSTS
        // sur des environnements de dev en HTTP.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy en mode Report-Only sur les réponses HTML uniquement.
        // Les navigateurs n'appliquent CSP qu'aux pages HTML (pas aux JSON ni aux PDF binaires).
        // Le front Nuxt sert ses propres pages : sa CSP doit être configurée dans nuxt.config.ts (TODO séparé).
        // Ici on couvre : Swagger /api/docs, preview templates admin, futures pages HTML back.
        $contentType = (string) $headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            // Politique permissive volontaire pendant la phase d'observation (Report-Only).
            // TODO LOT-2 : après 7j d'observation sans violation, basculer vers :
            //   script-src 'self' (retirer unsafe-inline / unsafe-eval)
            //   style-src 'self' (retirer unsafe-inline)
            //   et passer de Content-Security-Policy-Report-Only à Content-Security-Policy
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Swagger UI a besoin d'unsafe-eval
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "report-uri /api/security/csp-report",
            ]);
            $headers->set('Content-Security-Policy-Report-Only', $csp);
        }
    }
}

