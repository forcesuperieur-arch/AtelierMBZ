<?php

namespace App\EventSubscriber;

use App\Entity\ConfigAtelier;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bloque les routes API d'un module désactivé dans ConfigAtelier.featureModules.
 * Le front masque déjà les sections via hasSection() ; cette garde assure la
 * même règle côté serveur, y compris pour les ressources API Platform.
 */
final class FeatureModuleGuardSubscriber implements EventSubscriberInterface
{
    private const MODULE_PATH_PREFIXES = [
        'facturation' => [
            '/api/facturation',
            '/api/factures',
            '/api/ligne_factures',
            '/api/paiements',
        ],
        'devis' => [
            '/api/devis',
            '/api/ligne_devis',
        ],
    ];

    private const MODULE_PATH_PATTERNS = [
        'facturation' => [
            '#^/api/rendez-vous/\d+/(preview-facture|facturer)$#',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $atelierResolver,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Après le firewall (8), avant les listeners API Platform (4).
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $module = $this->matchModule($event->getRequest()->getPathInfo());
        if ($module === null || $this->isModuleEnabled($module)) {
            return;
        }

        throw new NotFoundHttpException(sprintf('Le module "%s" est désactivé.', $module));
    }

    private function matchModule(string $path): ?string
    {
        foreach (self::MODULE_PATH_PREFIXES as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix . '.')) {
                    return $module;
                }
            }
        }

        foreach (self::MODULE_PATH_PATTERNS as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $path)) {
                    return $module;
                }
            }
        }

        return null;
    }

    private function isModuleEnabled(string $module): bool
    {
        $atelierId = $this->atelierResolver->resolveAtelierId();
        $config = $atelierId
            ? $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId])
            : null;

        $modules = $config?->getFeatureModules() ?? ConfigAtelier::defaultFeatureModules();

        return !\array_key_exists($module, $modules) || $modules[$module] !== false;
    }
}
