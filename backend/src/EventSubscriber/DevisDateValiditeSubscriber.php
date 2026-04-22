<?php

namespace App\EventSubscriber;

use App\Entity\ConfigAtelier;
use App\Entity\Devis;
use App\Service\CurrentAtelierResolver;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;

/**
 * [SPRINT-6] [C11] — Remplace le +30j hardcodé du constructeur Devis par la valeur
 * ConfigAtelier.validiteDevisJours de l'atelier courant.
 * Fires APRÈS le lifecycle callback Devis::prePersist() (lifecycle callbacks avant event listeners).
 */
#[AsDoctrineListener(event: Events::prePersist)]
class DevisDateValiditeSubscriber
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $resolver,
    ) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Devis) {
            return;
        }

        // Priorité : atelierId déjà posé sur l'entité (par TenantSetterListener) ;
        // sinon, résoudre depuis le JWT via CurrentAtelierResolver.
        $atelierId = $entity->getAtelierId() ?? $this->resolver->getAtelierId();
        if (!$atelierId) {
            return; // Pas de contexte atelier — on garde le fallback +30j du constructeur
        }

        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
        $days = $config?->getValiditeDevisJours() ?? 30;

        // Override the date set by the entity constructor / prePersist lifecycle callback
        $entity->setDateValidite((new \DateTime())->modify("+{$days} days"));
    }
}
