<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\RapportIntervention;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CompanionRestitutionTest extends WebTestCase
{
    private function createRdvFixture(EntityManagerInterface $em, string $statut = 'termine'): RendezVous
    {
        $suffix = bin2hex(random_bytes(4));

        $client = (new Client())
            ->setAtelierId(1)
            ->setNom('Restitution-' . $suffix)
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('restitution-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($client)
            ->setPlaque('RS-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Kawasaki')
            ->setModele('Z900');

        $rdv = (new RendezVous())
            ->setAtelierId(1)
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('14:00:00'))
            ->setTypeIntervention('Révision')
            ->setStatut($statut);

        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->flush();

        return $rdv;
    }

    private function createRapportFixture(EntityManagerInterface $em, RendezVous $rdv): RapportIntervention
    {
        $rapport = new RapportIntervention();
        $rapport->setRendezVous($rdv);
        $rapport->setAtelierId(1);
        $rapport->setTravauxRealises('Changement huile et filtre');
        $rapport->setSignatureMecanicien('data:image/png;base64,mecanicien-signature');
        $rapport->setSigneMecanicienAt(new \DateTime());

        $em->persist($rapport);
        $em->flush();

        return $rapport;
    }

    public function testRapportRestitutionReturnsConflictWhenNotTermine(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'en_cours');
        $this->createRapportFixture($em, $rdv);

        $client->request('GET', '/api/companion/' . $rdv->getTokenSuivi() . '/rapport-restitution');

        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('terminée', $payload['error'] ?? '');
        $this->assertSame('en_cours', $payload['statut'] ?? null);
    }

    public function testRapportRestitutionReturnsSummaryWhenTermine(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'termine');
        $rapport = $this->createRapportFixture($em, $rdv);

        $client->request('GET', '/api/companion/' . $rdv->getTokenSuivi() . '/rapport-restitution');

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame($rdv->getId(), $payload['rdv']['id'] ?? null);
        $this->assertSame('termine', $payload['rdv']['statut'] ?? null);
        $this->assertSame($rapport->getId(), $payload['rapport']['id'] ?? null);
        $this->assertSame('Changement huile et filtre', $payload['rapport']['travaux_realises'] ?? null);
        $this->assertTrue($payload['rapport']['signature_mecanicien'] ?? false);
        $this->assertFalse($payload['rapport']['signature_client'] ?? true);
    }

    public function testSignatureRestitutionReturnsConflictWhenNotTermine(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'en_cours');
        $this->createRapportFixture($em, $rdv);

        $client->request(
            'POST',
            '/api/companion/' . $rdv->getTokenSuivi() . '/signature-restitution',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('terminée', $payload['error'] ?? '');
    }

    public function testSignatureRestitutionReturnsBadRequestForInvalidSignature(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'termine');
        $this->createRapportFixture($em, $rdv);

        $client->request(
            'POST',
            '/api/companion/' . $rdv->getTokenSuivi() . '/signature-restitution',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => 'not-an-image',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Signature invalide', $payload['error'] ?? null);
    }

    public function testSignatureRestitutionSavesBlobAndAppliesRestituerTransition(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'termine');
        $this->createRapportFixture($em, $rdv);

        $signatureBlob = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=';

        $client->request(
            'POST',
            '/api/companion/' . $rdv->getTokenSuivi() . '/signature-restitution',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => $signatureBlob,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($payload['success'] ?? false);
        $this->assertSame('restitue', $payload['rdv_statut'] ?? null);
        $this->assertSame('signe', $payload['rapport_statut'] ?? null);

        $em->clear();
        $savedRdv = $em->getRepository(RendezVous::class)->find($rdv->getId());
        $savedRapport = $em->getRepository(RapportIntervention::class)->findOneBy(
            ['rendezVous' => $savedRdv],
            ['id' => 'DESC']
        );

        $this->assertSame('restitue', $savedRdv->getStatut());
        $this->assertSame($signatureBlob, $savedRapport->getSignatureClient());
        $this->assertNotNull($savedRapport->getSigneClientAt());
    }

    public function testSignatureRestitutionReturnsConflictWhenAlreadySigned(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em, 'termine');
        $rapport = $this->createRapportFixture($em, $rdv);
        $rapport->setSignatureClient('data:image/png;base64,already-signed');
        $rapport->setSigneClientAt(new \DateTime());
        $em->flush();

        $client->request(
            'POST',
            '/api/companion/' . $rdv->getTokenSuivi() . '/signature-restitution',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('déjà été signé', $payload['error'] ?? '');
    }
}
