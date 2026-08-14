<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\Devis;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Service\BookingAtelierAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CockpitFileTravailTest extends WebTestCase
{
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user)];
    }

    private function makeAtelierSiege(EntityManagerInterface $em, string $suffix): Atelier
    {
        $atelier = (new Atelier())->setNom('FT' . $suffix)->setSlug('ft-' . $suffix)->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $config = new ConfigAtelier();
        $config->setAtelierId($atelier->getId());
        $modules = ConfigAtelier::defaultFeatureModules();
        $modules[BookingAtelierAccessService::FEATURE_RDV_SIEGE] = true;
        $config->setFeatureModules($modules);
        $em->persist($config);
        $em->flush();

        return $atelier;
    }

    private function makeSrc(EntityManagerInterface $em, string $suffix, int $atelierId): User
    {
        $src = (new User())->setUsername('ft-src-' . $suffix)->setEmail("ft-src-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierId);
        $em->persist($src);
        $em->flush();

        return $src;
    }

    public function testFileAnnulationsListsPendingRequestsAcrossAteliers(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->makeAtelierSiege($em, $suffix);
        $client = (new Client())->setNom('Annul' . $suffix)->setPrenom('X')->setTelephone('0600000010');
        $client->setAtelierId($atelier->getId());
        $vehicule = (new Vehicule())->setPlaque('AN-' . substr($suffix, 0, 3))->setClient($client);
        $em->persist($client);
        $em->persist($vehicule);
        $em->flush();

        $rdv = (new RendezVous())->setClient($client)->setVehicule($vehicule)
            ->setDateRdv(new \DateTime('+1 day'))->setHeureRdv(new \DateTime('09:00'))
            ->setTypeIntervention('Révision')->setStatut('confirme');
        // TenantSetterListener ne pose atelier_id que via le contexte utilisateur résolu d'une
        // vraie requête HTTP — en fixture directe (hors requête), il faut le poser à la main.
        $rdv->setAtelierId($atelier->getId());
        $rdv->setAnnulationDemandeeAt(new \DateTime('-2 hours'));
        $em->persist($rdv);
        $em->flush();

        $src = $this->makeSrc($em, $suffix, $atelier->getId());
        $em->clear();

        $webClient->request('GET', '/api/cockpit/file/annulations', [], [], $this->authHeaders($src));

        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $ids = array_column($payload['annulations'], 'id');
        $this->assertContains($rdv->getId(), $ids);
    }

    public function testFileRelancesListsDevisEnvoyeAcrossAteliers(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->makeAtelierSiege($em, $suffix);
        $client = (new Client())->setNom('Relance' . $suffix)->setPrenom('X')->setTelephone('0600000011');
        $client->setAtelierId($atelier->getId());
        $em->persist($client);
        $em->flush();

        $devis = new Devis();
        $devis->setClient($client);
        $devis->setAtelierId($atelier->getId());
        $devis->setStatut('envoye');
        $em->persist($devis);
        $em->flush();

        $src = $this->makeSrc($em, $suffix, $atelier->getId());
        $em->clear();

        $webClient->request('GET', '/api/cockpit/file/relances', [], [], $this->authHeaders($src));

        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $ids = array_column($payload['devis'], 'id');
        $this->assertContains($devis->getId(), $ids);
    }

    public function testReclamationCreateAddNoteAndQualify(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->makeAtelierSiege($em, $suffix);
        $client = (new Client())->setNom('Reclam' . $suffix)->setPrenom('X')->setTelephone('0600000012');
        $client->setAtelierId($atelier->getId());
        $em->persist($client);
        $em->flush();

        $src = $this->makeSrc($em, $suffix, $atelier->getId());
        $headers = $this->authHeaders($src);
        $em->clear();

        $webClient->request('POST', '/api/cockpit/reclamations', [], [], $headers, json_encode([
            'client_id' => $client->getId(),
            'sujet' => 'Moto rendue avec une rayure',
            'note' => 'Client très mécontent, rappel prévu demain.',
        ]));
        $this->assertSame(Response::HTTP_CREATED, $webClient->getResponse()->getStatusCode());
        $created = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('nouveau', $created['statut']);
        $this->assertCount(1, $created['notes']);

        $webClient->request('POST', '/api/cockpit/reclamations/' . $created['id'] . '/note', [], [], $headers, json_encode([
            'note' => 'Rappelé, geste commercial proposé.',
            'statut' => 'en_cours',
        ]));
        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $updated = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_cours', $updated['statut']);
        $this->assertCount(2, $updated['notes'], 'le cahier de bord accumule les notes, ne les remplace pas');

        $webClient->request('GET', '/api/cockpit/reclamations?statut=en_cours', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $webClient->getResponse()->getStatusCode());
        $list = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains($created['id'], array_column($list['reclamations'], 'id'));
    }
}
