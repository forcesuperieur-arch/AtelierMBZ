<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CompanionControllerTest extends WebTestCase
{
    private function createRdvFixture(EntityManagerInterface $em): RendezVous
    {
        $suffix = bin2hex(random_bytes(4));

        $client = (new Client())
            ->setNom('Companion')
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('companion-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setClient($client)
            ->setPlaque('CP-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Yamaha')
            ->setModele('Tracer 9')
            ->setVin('JYARN251000000' . strtoupper(substr($suffix, 0, 3)));

        $rdv = (new RendezVous())
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setAtelierId(1)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('09:30:00'))
            ->setTypeIntervention('Diagnostic')
            ->setKilometrage(12345)
            ->setEtatVehicule(json_encode(['rayures' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->flush();

        return $rdv;
    }

    public function testCompanionSignatureSignsAndFreezesOrdre(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em);

        $draftOr = (new OrdreReparation())
            ->setRendezVous($rdv)
            ->setNumeroOr('OR-' . $rdv->getId() . '-TEST')
            ->setTypeOr('initial')
            ->setStatut('brouillon');
        $draftOr->snapshotFromRdv();
        $em->persist($draftOr);
        $em->flush();

        $client->request(
            'POST',
            '/api/companion/' . $rdv->getTokenSuivi() . '/signature',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=',
                'clausesAcceptees' => ['cgv', 'garantie', 'rgpd'],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $em->clear();
        $savedRdv = $em->getRepository(RendezVous::class)->find($rdv->getId());
        $or = $em->getRepository(OrdreReparation::class)->findOneBy(['rendezVous' => $savedRdv], ['id' => 'DESC']);

        $this->assertNotNull($or);
        $this->assertSame('signe', $or->getStatut());
        $this->assertNotNull($or->getSignedAt());
        $this->assertNotEmpty($or->getSignedHash());
        $this->assertNotEmpty($or->getSignedSnapshot());
    }

    public function testCompanionPayloadReturnsTokenizedPublicPhotoUrl(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em);

        $photo = (new PhotoIntervention())
            ->setRendezVous($rdv)
            ->setFilename('companion-test-photo.jpg')
            ->setOriginalName('photo.jpg')
            ->setDescription('Test photo')
            ->setAtelierId(1);

        $em->persist($photo);
        $em->flush();

        $client->request('GET', '/api/companion/' . $rdv->getTokenSuivi());

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            '/api/public/photos/' . $rdv->getTokenSuivi() . '/companion-test-photo.jpg',
            $payload['photos'][0]['url'] ?? null
        );
        $this->assertArrayNotHasKey('vin', $payload['vehicule'] ?? []);
    }

    public function testCompanionVehiculeUpdateAcceptsVin(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $rdv = $this->createRdvFixture($em);

        $client->request(
            'PUT',
            '/api/companion/' . $rdv->getTokenSuivi() . '/vehicule',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'vin' => 'VF1LM1B0H12345678',
                'plaque' => 'AA-123-BB',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $em->clear();
        $savedRdv = $em->getRepository(RendezVous::class)->find($rdv->getId());

        $this->assertSame('VF1LM1B0H12345678', $savedRdv->getVehicule()->getVin());
    }
}
