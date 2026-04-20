<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\Prestation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RdvPrestationCatalogControllerTest extends WebTestCase
{
    public function testSuperAdminGetsAtelierCalculatedPricesFromSelectedRdvAtelier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierA = null;
        $atelierB = null;
        $configA = null;
        $configB = null;
        $prestationA = null;
        $prestationB = null;
        $user = null;

        try {
            $atelierA = (new Atelier())
                ->setNom('Tarif A ' . $suffix)
                ->setSlug('tarif-a-' . $suffix)
                ->setPlan('starter')
                ->setActif(true);
            $atelierB = (new Atelier())
                ->setNom('Tarif B ' . $suffix)
                ->setSlug('tarif-b-' . $suffix)
                ->setPlan('starter')
                ->setActif(true);
            $em->persist($atelierA);
            $em->persist($atelierB);
            $em->flush();

            $configA = (new ConfigAtelier())
                ->setAtelierId((int) $atelierA->getId())
                ->setTauxHoraireMoStandard('60.00');
            $configB = (new ConfigAtelier())
                ->setAtelierId((int) $atelierB->getId())
                ->setTauxHoraireMoStandard('120.00');
            $em->persist($configA);
            $em->persist($configB);

            $prestationA = (new Prestation())
                ->setAtelierId((int) $atelierA->getId())
                ->setCode('diag-a-' . $suffix)
                ->setNom('Diagnostic atelier A')
                ->setTypeTarif('horaire')
                ->setTempsEstimeMinutes(60)
                ->setTypeVehicule('tous')
                ->setPrixBaseHt('10.00')
                ->setPrixBaseTtc('12.00')
                ->setIsActive(1);
            $prestationB = (new Prestation())
                ->setAtelierId((int) $atelierB->getId())
                ->setCode('diag-b-' . $suffix)
                ->setNom('Diagnostic atelier B')
                ->setTypeTarif('horaire')
                ->setTempsEstimeMinutes(60)
                ->setTypeVehicule('tous')
                ->setPrixBaseHt('10.00')
                ->setPrixBaseTtc('12.00')
                ->setIsActive(1);
            $em->persist($prestationA);
            $em->persist($prestationB);

            $user = (new User())
                ->setUsername('sa-rdv-presta-' . $suffix)
                ->setEmail(sprintf('sa-rdv-presta-%s@example.test', $suffix))
                ->setHashedPassword('test')
                ->setRole('super_admin')
                ->setAtelierId((int) $atelierA->getId());
            $em->persist($user);
            $em->flush();

            $client->request(
                'GET',
                '/api/rdv/prestations-catalogue?atelier_id=' . $atelierA->getId() . '&type_moto=Trail&cylindree=900',
                [],
                [],
                $this->authHeaders($user)
            );

            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payloadA = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertNotEmpty($payloadA);
            $this->assertSame(72.0, (float) ($payloadA[0]['prix_base_ttc'] ?? 0));
            $this->assertSame((int) $atelierA->getId(), (int) ($payloadA[0]['atelier_id'] ?? 0));

            $client->request(
                'GET',
                '/api/rdv/prestations-catalogue?atelier_id=' . $atelierB->getId() . '&type_moto=Trail&cylindree=900',
                [],
                [],
                $this->authHeaders($user)
            );

            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payloadB = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertNotEmpty($payloadB);
            $this->assertSame(144.0, (float) ($payloadB[0]['prix_base_ttc'] ?? 0));
            $this->assertSame((int) $atelierB->getId(), (int) ($payloadB[0]['atelier_id'] ?? 0));
        } finally {
            $this->removeById($em, User::class, $user?->getId());
            $this->removeById($em, Prestation::class, $prestationA?->getId());
            $this->removeById($em, Prestation::class, $prestationB?->getId());
            $this->removeById($em, ConfigAtelier::class, $configA?->getId());
            $this->removeById($em, ConfigAtelier::class, $configB?->getId());
            $this->removeById($em, Atelier::class, $atelierA?->getId());
            $this->removeById($em, Atelier::class, $atelierB?->getId());
            $em->flush();
        }
    }

    private function removeById(EntityManagerInterface $em, string $className, ?int $id): void
    {
        if (!$id) {
            return;
        }

        $table = $em->getClassMetadata($className)->getTableName();
        $em->getConnection()->executeStatement(sprintf('DELETE FROM %s WHERE id = :id', $table), ['id' => $id]);
    }

    private function authHeaders(User $user): array
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        return [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ];
    }
}
