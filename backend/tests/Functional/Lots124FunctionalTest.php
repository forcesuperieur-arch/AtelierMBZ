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
 * Functional tests covering Lots 1, 2 and 4 delivered features.
 * These are NOT unit tests — they exercise full HTTP stacks + database state.
 */
class Lots124FunctionalTest extends WebTestCase
{
    // ════════════════════════════════════════════════════════════
    // LOT 1 — TenantFilterListener + Access Fixes
    // ════════════════════════════════════════════════════════════

    public function testTenantFilterIsolatesDataBetweenAteliers(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierA = (new Atelier())->setNom('Atelier A ' . $suffix)->setSlug('a-' . $suffix)->setPlan('starter')->setActif(true);
        $atelierB = (new Atelier())->setNom('Atelier B ' . $suffix)->setSlug('b-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelierA);
        $em->persist($atelierB);
        $em->flush();

        $pontA = (new Pont())->setNom('Pont A')->setAtelierId((int) $atelierA->getId())->setIsActive(1);
        $pontB = (new Pont())->setNom('Pont B')->setAtelierId((int) $atelierB->getId())->setIsActive(1);
        $em->persist($pontA);
        $em->persist($pontB);
        $em->flush();

        $userA = $this->createUser('user-a-' . $suffix, 'user', (int) $atelierA->getId());
        $em->persist($userA);
        $em->flush();

        $client->request('GET', '/api/ponts', [], [], $this->authHeaders($userA));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? [];
        $names = array_column($members, 'nom');

        $this->assertContains('Pont A', $names);
        $this->assertNotContains('Pont B', $names);

        $this->cleanup($em, [
            [User::class, (int) $userA->getId()],
            [Pont::class, (int) $pontA->getId()],
            [Pont::class, (int) $pontB->getId()],
            [Atelier::class, (int) $atelierA->getId()],
            [Atelier::class, (int) $atelierB->getId()],
        ]);
    }

    public function testTenantFilterBlocksDataLeakForUserWithoutAtelier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier L ' . $suffix)->setSlug('l-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $pont = (new Pont())->setNom('Pont L')->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($pont);
        $em->flush();

        // User without atelier_id and not super_admin
        $user = $this->createUser('user-orphan-' . $suffix, 'user', 9999);
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/ponts', [], [], $this->authHeaders($user));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? [];
        $names = array_column($members, 'nom');

        $this->assertNotContains('Pont L', $names, 'User from wrong atelier should not see any data');

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [User::class, (int) $user->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testSuperAdminBypassesTenantFilter(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier S ' . $suffix)->setSlug('s-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $pont = (new Pont())->setNom('Pont S')->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($pont);
        $em->flush();

        $superAdmin = $this->createUser('super-' . $suffix, 'super_admin', 0);
        $em->persist($superAdmin);
        $em->flush();

        $client->request('GET', '/api/ponts', [], [], $this->authHeaders($superAdmin));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? [];
        $names = array_column($members, 'nom');

        $this->assertContains('Pont S', $names, 'SuperAdmin should see all data regardless of atelier');

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [User::class, (int) $superAdmin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testHoraireAtelierGetCollectionIsReadableByRoleUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier H ' . $suffix)->setSlug('h-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $horaire = (new HoraireAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setJourSemaine(0)
            ->setIsOuvert(1)
            ->setHeureOuverture('08:00')
            ->setHeureFermeture('18:00');
        $em->persist($horaire);
        $em->flush();

        $user = $this->createUser('receptionnaire-' . $suffix, 'receptionnaire', (int) $atelier->getId());
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/horaire_ateliers', [], [], $this->authHeaders($user));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? [];
        $this->assertNotEmpty($members);

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $horaire->getId()],
            [User::class, (int) $user->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testHoraireAtelierGetByIdIsReadableByRoleUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier H1 ' . $suffix)->setSlug('h1-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $horaire = (new HoraireAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setJourSemaine(1)
            ->setIsOuvert(1)
            ->setHeureOuverture('09:00')
            ->setHeureFermeture('17:00');
        $em->persist($horaire);
        $em->flush();

        $user = $this->createUser('mecano-' . $suffix, 'mecanicien', (int) $atelier->getId());
        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/horaire_ateliers/' . $horaire->getId(), [], [], $this->authHeaders($user));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['jour_semaine'] ?? null);

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $horaire->getId()],
            [User::class, (int) $user->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicPrestationsCatalogReturnsPrestationsForAtelier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier P ' . $suffix)->setSlug('p-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $presta = (new Prestation())->setNom('Vidange')->setCode('VID-' . $suffix)->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($presta);
        $em->flush();

        $client->request('GET', '/api/public/prestations?atelier_id=' . $atelier->getId(), [], [], ['REMOTE_ADDR' => '10.0.0.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($payload, 'nom');
        $this->assertContains('Vidange', $names);

        $this->cleanup($em, [
            [Prestation::class, (int) $presta->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicPrestationsCatalogRequiresAtelierId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/public/prestations', [], [], ['REMOTE_ADDR' => '10.0.0.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    // ════════════════════════════════════════════════════════════
    // LOT 2 — Pont & Mécano Constraints
    // ════════════════════════════════════════════════════════════

    public function testOneMechanicCanOnlyBeAssignedToOnePontViaPatch(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier M ' . $suffix)->setSlug('m-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Jean')->setNom('Test ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont1 = (new Pont())->setNom('Pont 1')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont1);
        $em->flush();

        $pont2 = (new Pont())->setNom('Pont 2')->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($pont2);
        $em->flush();

        $admin = $this->createUser('admin-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $headers = array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/merge-patch+json']);
        $client->request(
            'PATCH',
            '/api/ponts/' . $pont2->getId(),
            [],
            [],
            $headers,
            json_encode(['mecanicien' => '/api/mecaniciens/' . $mecano->getId()], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('déjà assigné', $client->getResponse()->getContent());

        $this->cleanup($em, [
            [Pont::class, (int) $pont1->getId()],
            [Pont::class, (int) $pont2->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontCreationWithDuplicateMechanicFails(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier MC ' . $suffix)->setSlug('mc-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Jean')->setNom('Create ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont1 = (new Pont())->setNom('Pont 1C')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont1);
        $em->flush();

        $admin = $this->createUser('admin-c-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $headers = array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']);
        $client->request(
            'POST',
            '/api/ponts',
            [],
            [],
            $headers,
            json_encode([
                'nom' => 'Pont 2C',
                'atelier_id' => $atelier->getId(),
                'mecanicien' => '/api/mecaniciens/' . $mecano->getId(),
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('déjà assigné', $client->getResponse()->getContent());

        $this->cleanup($em, [
            [Pont::class, (int) $pont1->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignWithoutFutureRdvsSucceeds(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier UOK ' . $suffix)->setSlug('uok-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('OK ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont OK')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $admin = $this->createUser('admin-uok-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
        $this->assertNull($payload['pont']['mecanicien']);

        $this->cleanup($em, [
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPontUnassignWithFutureRdvsReturnsConflictAndSuggestions(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier U ' . $suffix)->setSlug('u-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('Unassign ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $mecano2 = (new Mecanicien())->setPrenom('Pierre')->setNom('Backup ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano2);
        $em->flush();

        $pont = (new Pont())->setNom('Pont U')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        $pont2 = (new Pont())->setNom('Pont U2')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano2);
        $em->persist($pont2);
        $em->flush();

        // Open hours for the next 7 days so SlotService can suggest alternatives
        $rdvDayIndex = (int) (new \DateTime('+1 day'))->format('N') - 1;
        for ($i = 0; $i < 7; $i++) {
            $dayIdx = ($rdvDayIndex + $i) % 7;
            $existing = $em->getRepository(HoraireAtelier::class)->findOneBy(['atelierId' => $atelier->getId(), 'jourSemaine' => $dayIdx]);
            if (!$existing) {
                $h = (new HoraireAtelier())
                    ->setAtelierId((int) $atelier->getId())
                    ->setJourSemaine($dayIdx)
                    ->setIsOuvert(1)
                    ->setHeureOuverture('08:00')
                    ->setHeureFermeture('18:00');
                $em->persist($h);
            }
        }
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
            ->setStatut('confirme')
            ->setPont($pont)
            ->setMecanicien($mecano);
        $em->persist($rdv);
        $em->flush();

        $admin = $this->createUser('admin-u-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('POST', '/api/ponts/' . $pont->getId() . '/unassign', [], [], $this->authHeaders($admin));
        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['canUnassign']);
        $this->assertNotEmpty($payload['futureRdvs']);
        $this->assertNotEmpty($payload['suggestedSlots']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Pont::class, (int) $pont->getId()],
            [Pont::class, (int) $pont2->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [Mecanicien::class, (int) $mecano2->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testInactivePontIsExcludedFromSlots(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier SI ' . $suffix)->setSlug('si-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $mecano = (new Mecanicien())->setPrenom('Marc')->setNom('Slot ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $activePont = (new Pont())->setNom('Pont Actif')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $inactivePont = (new Pont())->setNom('Pont Inactif')->setAtelierId((int) $atelier->getId())->setIsActive(0)->setMecanicien($mecano);
        $em->persist($activePont);
        $em->persist($inactivePont);
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

        $admin = $this->createUser('admin-si-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.0.3.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $slots = $payload['slots'][$tomorrow] ?? [];
        $pontIds = array_column($slots, 'pont_id');

        $this->assertContains((int) $activePont->getId(), $pontIds, 'Active pont should appear in slots');
        $this->assertNotContains((int) $inactivePont->getId(), $pontIds, 'Inactive pont should NOT appear in slots');

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [Pont::class, (int) $activePont->getId()],
            [Pont::class, (int) $inactivePont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // LOT 4 — Public Booking, Suivi & Commandes
    // ════════════════════════════════════════════════════════════

    public function testPublicBookingDisabledByFeatureFlag(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier F ' . $suffix)->setSlug('f-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $config = (new ConfigAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setFeatureModules([...ConfigAtelier::defaultFeatureModules(), 'public_booking' => false]);
        $em->persist($config);
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

        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.0.4.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payload['bookingEnabled']);
        $this->assertArrayHasKey('slots', $payload);

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [ConfigAtelier::class, (int) $config->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicBookingEnabledByFeatureFlag(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier FE ' . $suffix)->setSlug('fe-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $config = (new ConfigAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setFeatureModules([...ConfigAtelier::defaultFeatureModules(), 'public_booking' => true]);
        $em->persist($config);
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

        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.0.5.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['bookingEnabled']);

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [ConfigAtelier::class, (int) $config->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicSuiviFindsRdvByEmailAndPhone(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier S ' . $suffix)->setSlug('s-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $clientEntity = (new Client())
            ->setNom('Dupont')
            ->setPrenom('Jean')
            ->setEmail('jean.dupont.' . $suffix . '@example.test')
            ->setTelephone('0612345678');
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
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.6.' . random_int(1, 254)],
            json_encode([
                'email' => 'jean.dupont.' . $suffix . '@example.test',
                'telephone' => '0612345678',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('entretien', $payload['rdv']['type_intervention']);
        $this->assertSame('confirme', $payload['rdv']['statut']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicSuiviReturns404ForUnknownClient(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.7.' . random_int(1, 254)],
            json_encode(['email' => 'unknown@example.test', 'telephone' => '0600000000'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testPublicSuiviReturns404ForFinishedRdv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier SF ' . $suffix)->setSlug('sf-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $clientEntity = (new Client())
            ->setNom('Martin')
            ->setPrenom('Pierre')
            ->setEmail('pierre.martin.' . $suffix . '@example.test')
            ->setTelephone('0699887766');
        $em->persist($clientEntity);
        $em->flush();

        $rdv = (new RendezVous())
            ->setAtelierId((int) $atelier->getId())
            ->setClient($clientEntity)
            ->setDateRdv(new \DateTime('-2 days'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('revision')
            ->setStatut('termine');
        $em->persist($rdv);
        $em->flush();

        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.8.' . random_int(1, 254)],
            json_encode([
                'email' => 'pierre.martin.' . $suffix . '@example.test',
                'telephone' => '0699887766',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv->getId()],
            [Client::class, (int) $clientEntity->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testPublicSuiviRequiresBothFields(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.0.9.' . random_int(1, 254)],
            json_encode(['email' => 'test@example.test'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testRdvCreationSyncsCommandes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier C ' . $suffix)->setSlug('c-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-c-' . $suffix, 'admin', (int) $atelier->getId());
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
                'client_prenom' => 'Commande',
                'client_telephone' => '0698765432',
                'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
                'heure_debut' => '09:00',
                'type_intervention' => 'revision',
                'commandes' => ['CMD-001', 'CMD-002'],
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

    public function testRdvUpdateCommandesViaEndpoint(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier UC ' . $suffix)->setSlug('uc-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-uc-' . $suffix, 'admin', (int) $atelier->getId());
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
                'client_prenom' => 'Update',
                'client_telephone' => '0698765432',
                'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
                'heure_debut' => '09:00',
                'type_intervention' => 'revision',
                'commandes' => ['CMD-001', 'CMD-002'],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $rdvPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $rdvId = $rdvPayload['id'];

        // Update commandes via custom endpoint
        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdvId . '/commandes',
            [],
            [],
            array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['commandes' => ['CMD-003']], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertContains('CMD-003', $updated['commandes']);
        $this->assertNotContains('CMD-001', $updated['commandes']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdvId],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testRdvCreationWithEmptyCommandesClearsExisting(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier CE ' . $suffix)->setSlug('ce-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-ce-' . $suffix, 'admin', (int) $atelier->getId());
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
                'client_prenom' => 'Clear',
                'client_telephone' => '0698765432',
                'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
                'heure_debut' => '09:00',
                'type_intervention' => 'revision',
                'commandes' => ['CMD-001'],
            ], JSON_THROW_ON_ERROR)
        );

        $rdvPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $rdvId = $rdvPayload['id'];
        $this->assertContains('CMD-001', $rdvPayload['commandes']);

        // Clear via custom endpoint
        $client->request(
            'POST',
            '/api/rendez-vous/' . $rdvId . '/commandes',
            [],
            [],
            array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['commandes' => []], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEmpty($updated['commandes']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdvId],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    public function testConfigAtelierDefaultFeatureModulesIncludesPublicBooking(): void
    {
        $modules = ConfigAtelier::defaultFeatureModules();
        $this->assertArrayHasKey('public_booking', $modules);
        $this->assertFalse($modules['public_booking']);
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
