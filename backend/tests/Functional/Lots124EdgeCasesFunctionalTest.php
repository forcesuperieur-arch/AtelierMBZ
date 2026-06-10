<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Client;
use App\Entity\ConfigAtelier;
use App\Entity\HoraireAtelier;
use App\Entity\Mecanicien;
use App\Entity\Pont;
use App\Entity\Prestation;
use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edge-case functional tests for Lots 1, 2 and 4.
 * Covers improbable and boundary scenarios.
 */
class Lots124EdgeCasesFunctionalTest extends WebTestCase
{
    // ════════════════════════════════════════════════════════════
    // LOT 1 — Edge Cases
    // ════════════════════════════════════════════════════════════

    public function testPublicEndpointBypassesTenantFilter(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier Pub ' . $suffix)->setSlug('pub-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $presta = (new Prestation())->setNom('Pub')->setCode('PUB-' . $suffix)->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($presta);
        $em->flush();

        // No auth headers — public endpoint should work
        $client->request('GET', '/api/public/prestations?atelier_id=' . $atelier->getId(), [], [], ['REMOTE_ADDR' => '10.1.0.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [Prestation::class, (int) $presta->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicPrestationsCatalogFiltersInactivePrestations(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier PF ' . $suffix)->setSlug('pf-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $active = (new Prestation())->setNom('Active')->setCode('ACT-' . $suffix)->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $inactive = (new Prestation())->setNom('Inactive')->setCode('INA-' . $suffix)->setAtelierId((int) $atelier->getId())->setIsActive(0);
        $em->persist($active);
        $em->persist($inactive);
        $em->flush();

        $client->request('GET', '/api/public/prestations?atelier_id=' . $atelier->getId(), [], [], ['REMOTE_ADDR' => '10.1.1.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($payload, 'nom');
        $this->assertContains('Active', $names);
        $this->assertNotContains('Inactive', $names, 'Inactive prestations should be excluded from public catalog');

        $this->cleanup($em, [
            [Prestation::class, (int) $active->getId()],
            [Prestation::class, (int) $inactive->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testHoraireAtelierPostIsForbiddenForRoleUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier HF ' . $suffix)->setSlug('hf-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $user = $this->createUser('user-hf-' . $suffix, 'receptionnaire', (int) $atelier->getId());
        $em->persist($user);
        $em->flush();

        $headers = array_merge($this->authHeaders($user), ['CONTENT_TYPE' => 'application/json']);
        $client->request('POST', '/api/horaire_ateliers', [], [], $headers, json_encode([
            'atelier_id' => $atelier->getId(),
            'jour_semaine' => 0,
            'is_ouvert' => 1,
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [User::class, (int) $user->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // LOT 2 — Edge Cases
    // ════════════════════════════════════════════════════════════

    public function testPontUnassignWithoutMechanicReturns400(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier U0 ' . $suffix)->setSlug('u0-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $pont = (new Pont())->setNom('Pont U0')->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($pont);
        $em->flush();

        $admin = $this->createUser('admin-u0-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignWithCancelledFutureRdvsSucceeds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier UC ' . $suffix)->setSlug('uc-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('Cancel ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont UC')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $clientEntity = (new Client())->setNom('Client')->setPrenom('Test')->setTelephone('0612345678');
        $em->persist($clientEntity);
        $em->flush();

        $rdv = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('+1 day'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('revision')
            ->setStatut('annule')
            ->setPont($pont)
            ->setMecanicien($mecano);
        $em->persist($rdv);
        $em->flush();

        $admin = $this->createUser('admin-uc-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignWithFinishedFutureRdvsSucceeds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier UF ' . $suffix)->setSlug('uf-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('Finish ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont UF')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $clientEntity = (new Client())->setNom('Client')->setPrenom('Test')->setTelephone('0612345678');
        $em->persist($clientEntity);
        $em->flush();

        $rdv = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('+1 day'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('revision')
            ->setStatut('termine')
            ->setPont($pont)
            ->setMecanicien($mecano);
        $em->persist($rdv);
        $em->flush();

        $admin = $this->createUser('admin-uf-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignNonExistentPontReturns404(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier U404 ' . $suffix)->setSlug('u404-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-u404-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/999999/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignAsNonAdminReturns403(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier U403 ' . $suffix)->setSlug('u403-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('403 ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont U403')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $user = $this->createUser('user-u403-' . $suffix, 'receptionnaire', (int) $atelier->getId());
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($user));
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $user->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPatchPontWithNullMechanicUnassigns(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier PN ' . $suffix)->setSlug('pn-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('Null ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont PN')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $admin = $this->createUser('admin-pn-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $headers = array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/merge-patch+json']);
        $client->request(
            'PATCH',
            '/api/ponts/' . $pont->getId(),
            [],
            [],
            $headers,
            json_encode(['mecanicien' => null], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $em->refresh($pont);
        $this->assertNull($pont->getMecanicien());

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testSlotServiceExcludesPontWithoutMechanic(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier SM ' . $suffix)->setSlug('sm-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Marc')->setNom('Slot ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $withMecano = (new Pont())->setNom('Pont Avec')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $withoutMecano = (new Pont())->setNom('Pont Sans')->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($withMecano);
        $em->persist($withoutMecano);
        $em->flush();

        $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
        $dayIdx = (int) (new \DateTime('+1 day'))->format('N') - 1;
        $h = (new HoraireAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setJourSemaine($dayIdx)
            ->setIsOuvert(1)
            ->setHeureOuverture('08:00')
            ->setHeureFermeture('18:00');
        $em->persist($h);
        $em->flush();

        $admin = $this->createUser('admin-sm-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.1.2.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $slots = $payload['slots'][$tomorrow] ?? [];
        $pontIds = array_column($slots, 'pont_id');

        $this->assertContains((int) $withMecano->getId(), $pontIds);
        $this->assertNotContains((int) $withoutMecano->getId(), $pontIds, 'Pont without mechanic should be excluded from slots');

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [Pont::class, (int) $withMecano->getId()],
            [Pont::class, (int) $withoutMecano->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // LOT 4 — Edge Cases
    // ════════════════════════════════════════════════════════════

    public function testPublicBookingDefaultsToFalseWhenNoConfig(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier NC ' . $suffix)->setSlug('nc-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        // No ConfigAtelier created for this atelier
        $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
        $dayIdx = (int) (new \DateTime('+1 day'))->format('N') - 1;
        $h = (new HoraireAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setJourSemaine($dayIdx)
            ->setIsOuvert(1)
            ->setHeureOuverture('08:00')
            ->setHeureFermeture('18:00');
        $em->persist($h);
        $em->flush();

        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.1.3.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['bookingEnabled'], 'No config should default to false');

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicSuiviReturnsMostRecentActiveRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier MR ' . $suffix)->setSlug('mr-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $clientEntity = (new Client())
            ->setNom('Dupont')
            ->setPrenom('Jean')
            ->setEmail('jean.mr.' . $suffix . '@example.test')
            ->setTelephone('0611111111');
        $em->persist($clientEntity);
        $em->flush();

        $older = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('+1 day'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('revision')
            ->setStatut('confirme');
        $em->persist($older);
        $em->flush();

        $newer = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('+3 days'))
            ->setHeureRdv(new \DateTime('14:00'))
            ->setTypeIntervention('entretien')
            ->setStatut('confirme');
        $em->persist($newer);
        $em->flush();

        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.1.4.' . random_int(1, 254)],
            json_encode([
                'email' => 'jean.mr.' . $suffix . '@example.test',
                'telephone' => '0611111111',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('entretien', $payload['rdv']['type_intervention'], 'Should return the most recent active RDV');

        $this->cleanup($em, [
            [RendezVous::class, (int) $newer->getId()],
            [RendezVous::class, (int) $older->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicSuiviWrongPhoneReturns404(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier WP ' . $suffix)->setSlug('wp-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $clientEntity = (new Client())
            ->setNom('Martin')
            ->setPrenom('Pierre')
            ->setEmail('pierre.wp.' . $suffix . '@example.test')
            ->setTelephone('0699887766');
        $em->persist($clientEntity);
        $em->flush();

        $rdv = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('+2 days'))
            ->setHeureRdv(new \DateTime('14:00'))
            ->setTypeIntervention('entretien')
            ->setStatut('confirme');
        $em->persist($rdv);
        $em->flush();

        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.1.5.' . random_int(1, 254)],
            json_encode([
                'email' => 'pierre.wp.' . $suffix . '@example.test',
                'telephone' => '0600000000',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testRdvCreationWithDuplicateCommandesDedupes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier DD ' . $suffix)->setSlug('dd-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-dd-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/api/rendez-vous',
            [],
            [],
            array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'client_nom' => 'Test',
                'client_prenom' => 'Dedup',
                'client_telephone' => '0698765432',
                'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
                'heure_debut' => '09:00',
                'type_intervention' => 'revision',
                'commandes' => ['CMD-001', 'CMD-001', 'CMD-002'],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('CMD-001', $payload['commandes']);
        $this->assertContains('CMD-002', $payload['commandes']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $payload['id']],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testRdvCreationWithNullCommandesIgnores(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier NL ' . $suffix)->setSlug('nl-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-nl-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/api/rendez-vous',
            [],
            [],
            array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'client_nom' => 'Test',
                'client_prenom' => 'Null',
                'client_telephone' => '0698765432',
                'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
                'heure_debut' => '09:00',
                'type_intervention' => 'revision',
                'commandes' => null,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEmpty($payload['commandes'] ?? []);

        $this->cleanup($em, [
            [RendezVous::class, (int) $payload['id']],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════

    private function createUser(string $username, string $role, int $atelierId): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($username . '@example.test')
            ->setHashedPassword('test')
            ->setRole($role)
            ->setAtelierId($atelierId);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        return [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * @param array<array{0: string, 1: int}> $items
     */
    private function cleanup(EntityManagerInterface $em, array $items): void
    {
        foreach ($items as [$class, $id]) {
            $entity = $em->getRepository($class)->find($id);
            if ($entity !== null) {
                $em->remove($entity);
            }
        }
        $em->flush();
    }
}
