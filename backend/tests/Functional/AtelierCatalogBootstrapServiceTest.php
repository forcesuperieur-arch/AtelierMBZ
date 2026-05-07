<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Prestation;
use App\Service\AtelierCatalogBootstrapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AtelierCatalogBootstrapServiceTest extends KernelTestCase
{
    public function testEnsurePrestationsForAtelierClonesCatalogEvenWhenTenantFilterIsEnabled(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $service = $container->get(AtelierCatalogBootstrapService::class);
        $suffix = bin2hex(random_bytes(4));

        $sourceAtelier = (new Atelier())
            ->setNom('Bootstrap Source ' . $suffix)
            ->setSlug('bootstrap-source-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);

        $targetAtelier = (new Atelier())
            ->setNom('Bootstrap Target ' . $suffix)
            ->setSlug('bootstrap-target-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);

        $sourcePrestation = (new Prestation())
            ->setCode('BOOT_' . strtoupper($suffix))
            ->setNom('Prestation bootstrap ' . $suffix)
            ->setCategorie('entretien')
            ->setPrixBaseHt('50.00')
            ->setPrixBaseTtc('60.00')
            ->setTempsEstimeMinutes(45)
            ->setTypeTarif('forfait')
            ->setAtelierId(null)
            ->setIsActive(1);

        $em->persist($sourceAtelier);
        $em->persist($targetAtelier);
        $em->flush();

        $sourcePrestation->setAtelierId((int) $sourceAtelier->getId());
        $em->persist($sourcePrestation);
        $em->flush();

        $filters = $em->getFilters();
        if (!$filters->isEnabled('tenant_filter')) {
            $filters->enable('tenant_filter');
        }
        $filters->getFilter('tenant_filter')->setParameter('atelier_id', (int) $targetAtelier->getId());

        try {
            $created = $service->ensurePrestationsForAtelier((int) $targetAtelier->getId());

            $filters->disable('tenant_filter');
            $em->clear();

            $clonedPrestations = $em->getRepository(Prestation::class)->findBy(['atelierId' => (int) $targetAtelier->getId()]);

            $this->assertGreaterThanOrEqual(1, $created);
            $this->assertNotEmpty($clonedPrestations);
            $this->assertContainsOnlyInstancesOf(Prestation::class, $clonedPrestations);
        } finally {
            if ($filters->isEnabled('tenant_filter')) {
                $filters->disable('tenant_filter');
            }

            foreach ($em->getRepository(Prestation::class)->findBy(['atelierId' => (int) $targetAtelier->getId()]) as $entity) {
                $em->remove($entity);
            }

            $sourceEntity = $em->getRepository(Prestation::class)->findOneBy(['code' => 'BOOT_' . strtoupper($suffix)]);
            if ($sourceEntity !== null) {
                $em->remove($sourceEntity);
            }

            $sourceAtelierEntity = $em->getRepository(Atelier::class)->find($sourceAtelier->getId());
            if ($sourceAtelierEntity !== null) {
                $em->remove($sourceAtelierEntity);
            }

            $targetAtelierEntity = $em->getRepository(Atelier::class)->find($targetAtelier->getId());
            if ($targetAtelierEntity !== null) {
                $em->remove($targetAtelierEntity);
            }

            $em->flush();
        }
    }

    public function testEnsurePrestationsUsesDefaultCatalogWhenNoSourceAtelier(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $service = $container->get(AtelierCatalogBootstrapService::class);
        $suffix = bin2hex(random_bytes(4));

        // Atelier isolé — aucun autre atelier n'a de prestations (ou ce nouvel atelier est seul)
        $targetAtelier = (new Atelier())
            ->setNom('Isolated ' . $suffix)
            ->setSlug('isolated-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);

        $em->persist($targetAtelier);
        $em->flush();

        $filters = $em->getFilters();
        if ($filters->isEnabled('tenant_filter')) {
            $filters->disable('tenant_filter');
        }

        // Supprimer les prestations éventuelles de cet atelier avant le test
        foreach ($em->getRepository(Prestation::class)->findBy(['atelierId' => (int) $targetAtelier->getId()]) as $p) {
            $em->remove($p);
        }
        $em->flush();

        try {
            $created = $service->ensurePrestationsForAtelier((int) $targetAtelier->getId());

            $em->clear();
            $prestations = $em->getRepository(Prestation::class)->findBy(['atelierId' => (int) $targetAtelier->getId()]);

            $this->assertGreaterThan(0, $created, 'Le bootstrap doit créer au moins une prestation');

            // Requête directe DBAL pour contourner tout filtre Doctrine
            $conn = $em->getConnection();
            $count = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM prestations WHERE atelier_id = ?',
                [(int) $targetAtelier->getId()]
            );
            $this->assertGreaterThan(0, $count, 'Les prestations doivent être persistées en base');

            // Idempotence : un deuxième appel ne doit rien créer
            $createdAgain = $service->ensurePrestationsForAtelier((int) $targetAtelier->getId());
            $this->assertSame(0, $createdAgain, 'ensurePrestationsForAtelier doit être idempotent');
        } finally {
            foreach ($em->getRepository(Prestation::class)->findBy(['atelierId' => (int) $targetAtelier->getId()]) as $p) {
                $em->remove($p);
            }
            $targetAtelierEntity = $em->getRepository(Atelier::class)->find($targetAtelier->getId());
            if ($targetAtelierEntity !== null) {
                $em->remove($targetAtelierEntity);
            }
            $em->flush();
        }
    }
}
