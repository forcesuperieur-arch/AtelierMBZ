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
 * End-to-end functional tests covering full user journeys (A to Z)
 * across Lots 1, 2 and 4 delivered features.
 */
class Lots124EndToEndFlowsFunctionalTest extends WebTestCase
{
    // ════════════════════════════════════════════════════════════
    // PARCOURS A — Chef d'atelier : création atelier → pont →
    // mécano → assignation → RDV → désassignation bloquée
    // ════════════════════════════════════════════════════════════

    public function testChefAtelierFullWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        // 1. Créer l'atelier
        $atelier = (new Atelier())->setNom('Atelier Chef ' . $suffix)->setSlug('chef-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        // 2. Créer le chef d'atelier
        $chef = $this->createUser('chef-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($chef);
        $em->flush();

        // 3. Créer un pont (sans mécano)
        $headers = array_merge($this->authHeaders($chef), ['CONTENT_TYPE' => 'application/json']);
        $client->request('POST', '/api/ponts', [], [], $headers, json_encode([
            'nom' => 'Pont Chef',
            'atelier_id' => $atelier->getId(),
            'is_active' => 1,
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $pontPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $pontId = $pontPayload['id'];

        // 4. Créer un mécano
        $mecano = (new Mecanicien())->setPrenom('Jean')->setNom('Mécano ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        // 5. Assigner le mécano au pont
        $patchHeaders = array_merge($this->authHeaders($chef), ['CONTENT_TYPE' => 'application/merge-patch+json']);
        $client->request('PATCH', '/api/ponts/' . $pontId, [], [], $patchHeaders, json_encode([
            'mecanicien' => '/api/mecaniciens/' . $mecano->getId(),
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 6. Créer un client
        $clientEntity = (new Client())->setNom('Dupont')->setPrenom('Marie')->setTelephone('0612345678')->setAtelierId((int) $atelier->getId());
        $em->persist($clientEntity);
        $em->flush();

        // 7. Créer un RDV sur ce pont
        $client->request('POST', '/api/rendez-vous', [], [], $headers, json_encode([
            'client_id' => $clientEntity->getId(),
            'date_rdv' => (new \DateTime('+2 days'))->format('Y-m-d'),
            'heure_debut' => '10:00',
            'type_intervention' => 'revision',
            'pont_id' => $pontId,
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $rdvPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $rdvId = $rdvPayload['id'];
        $this->assertSame('revision', $rdvPayload['type_intervention']);

        // 8. Essayer de désassigner le mécano → bloqué car RDV futur
        $client->request('POST', '/api/ponts/' . $pontId . '/unassign', [], [], $this->authHeaders($chef));
        $this->assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        $unassignPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($unassignPayload['canUnassign']);
        $this->assertNotEmpty($unassignPayload['futureRdvs']);
        $this->assertSame($rdvId, $unassignPayload['futureRdvs'][0]['id']);

        // 9. Annuler le RDV
        $rdv = $em->getRepository(RendezVous::class)->find($rdvId);
        $rdv->setStatut('annule');
        $em->flush();

        // 10. Réessayer la désassignation → OK cette fois
        $client->request('POST', '/api/ponts/' . $pontId . '/unassign', [], [], $this->authHeaders($chef));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $finalPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($finalPayload['success']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdvId],
            [Client::class, (int) $clientEntity->getId()],
            [Pont::class, (int) $pontId],
            [Mecanicien::class, (int) $mecano->getId()],
            [User::class, (int) $chef->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // PARCOURS B — Client public : booking complet + suivi
    // ════════════════════════════════════════════════════════════

    public function testClientPublicBookingAndTrackingWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        // 1. Créer l'atelier avec public_booking activé
        $atelier = (new Atelier())->setNom('Atelier Pub ' . $suffix)->setSlug('pub-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $config = (new ConfigAtelier())
            ->setAtelierId((int) $atelier->getId())
            ->setFeatureModules([...ConfigAtelier::defaultFeatureModules(), 'public_booking' => true]);
        $em->persist($config);
        $em->flush();

        // 2. Créer horaires ouverts demain
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

        // 3. Créer pont + mécano pour avoir des créneaux
        $mecano = (new Mecanicien())->setPrenom('Paul')->setNom('Public ' . $suffix)->setAtelierId((int) $atelier->getId());
        $em->persist($mecano);
        $em->flush();

        $pont = (new Pont())->setNom('Pont Public')->setAtelierId((int) $atelier->getId())->setIsActive(1)->setMecanicien($mecano);
        $em->persist($pont);
        $em->flush();

        // 4. Créer prestation active
        $presta = (new Prestation())->setNom('Révision')->setCode('REV-' . $suffix)->setAtelierId((int) $atelier->getId())->setIsActive(1);
        $em->persist($presta);
        $em->flush();

        // 5. Vérifier que le booking est activé
        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.2.0.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $slotsPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($slotsPayload['bookingEnabled']);
        $this->assertNotEmpty($slotsPayload['slots'][$tomorrow]);
        $firstSlot = $slotsPayload['slots'][$tomorrow][0];

        // 6. Vérifier le catalogue prestations
        $client->request('GET', '/api/public/prestations?atelier_id=' . $atelier->getId(), [], [], ['REMOTE_ADDR' => '10.2.1.' . random_int(1, 254)]);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $prestaPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($prestaPayload);

        // 7. Réserver un créneau
        $client->request(
            'POST',
            '/api/public/booking',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.2.2.' . random_int(1, 254)],
            json_encode([
                'nom' => 'Dupont',
                'prenom' => 'Marie',
                'telephone' => '0612345678',
                'email' => 'marie.dupont.' . $suffix . '@example.test',
                'date_rdv' => $tomorrow,
                'heure_rdv' => $firstSlot['heure'],
                'type_intervention' => 'Révision',
                'atelier_id' => $atelier->getId(),
                'pont_id' => $firstSlot['pont_id'],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $bookingPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token_suivi', $bookingPayload);
        $rdvId = $bookingPayload['id'];

        // 8. Suivre le RDV par email + téléphone
        $client->request(
            'POST',
            '/api/public/suivi',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '10.2.3.' . random_int(1, 254)],
            json_encode([
                'email' => 'marie.dupont.' . $suffix . '@example.test',
                'telephone' => '0612345678',
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $suiviPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Révision', $suiviPayload['rdv']['type_intervention']);
        $this->assertSame($tomorrow, $suiviPayload['rdv']['date']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdvId],
            [Prestation::class, (int) $presta->getId()],
            [Pont::class, (int) $pont->getId()],
            [Mecanicien::class, (int) $mecano->getId()],
            [HoraireAtelier::class, (int) $h->getId()],
            [ConfigAtelier::class, (int) $config->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // PARCOURS C — Tenant isolation : 2 ateliers complets
    // ════════════════════════════════════════════════════════════

    public function testMultiAtelierIsolationFullWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        // Atelier 1
        $atelier1 = (new Atelier())->setNom('Atelier 1 ' . $suffix)->setSlug('a1-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier1);
        $em->flush();

        $user1 = $this->createUser('user1-' . $suffix, 'receptionnaire', (int) $atelier1->getId());
        $em->persist($user1);
        $em->flush();

        $pont1 = (new Pont())->setNom('Pont 1')->setAtelierId((int) $atelier1->getId())->setIsActive(1);
        $em->persist($pont1);
        $em->flush();

        $client1 = (new Client())->setNom('Client1')->setPrenom('A')->setTelephone('0600000001');
        $em->persist($client1);
        $em->flush();

        $rdv1 = (new RendezVous())
            ->setAtelierId((int) $atelier1->getId())
            ->setClient($client1)
            ->setDateRdv(new \DateTime('+1 day'))
            ->setHeureRdv(new \DateTime('10:00'))
            ->setTypeIntervention('revision')
            ->setStatut('confirme')
            ->setPont($pont1);
        $em->persist($rdv1);
        $em->flush();

        // Atelier 2
        $atelier2 = (new Atelier())->setNom('Atelier 2 ' . $suffix)->setSlug('a2-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier2);
        $em->flush();

        $user2 = $this->createUser('user2-' . $suffix, 'receptionnaire', (int) $atelier2->getId());
        $em->persist($user2);
        $em->flush();

        $pont2 = (new Pont())->setNom('Pont 2')->setAtelierId((int) $atelier2->getId())->setIsActive(1);
        $em->persist($pont2);
        $em->flush();

        $client2 = (new Client())->setNom('Client2')->setPrenom('B')->setTelephone('0600000002');
        $em->persist($client2);
        $em->flush();

        $rdv2 = (new RendezVous())
            ->setAtelierId((int) $atelier2->getId())
            ->setClient($client2)
            ->setDateRdv(new \DateTime('+2 days'))
            ->setHeureRdv(new \DateTime('14:00'))
            ->setTypeIntervention('entretien')
            ->setStatut('confirme')
            ->setPont($pont2);
        $em->persist($rdv2);
        $em->flush();

        // User 1 ne voit que son atelier
        $client->request('GET', '/api/ponts', [], [], $this->authHeaders($user1));
        $ponts1 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names1 = array_column($ponts1['hydra:member'] ?? $ponts1['member'] ?? [], 'nom');
        $this->assertContains('Pont 1', $names1);
        $this->assertNotContains('Pont 2', $names1);

        $client->request('GET', '/api/rendez-vous', [], [], $this->authHeaders($user1));
        $rdvs1 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $types1 = array_column($rdvs1['hydra:member'] ?? $rdvs1['member'] ?? [], 'type_intervention');
        $this->assertContains('revision', $types1);
        $this->assertNotContains('entretien', $types1);

        // User 2 ne voit que son atelier
        $client->request('GET', '/api/ponts', [], [], $this->authHeaders($user2));
        $ponts2 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names2 = array_column($ponts2['hydra:member'] ?? $ponts2['member'] ?? [], 'nom');
        $this->assertContains('Pont 2', $names2);
        $this->assertNotContains('Pont 1', $names2);

        $client->request('GET', '/api/rendez-vous', [], [], $this->authHeaders($user2));
        $rdvs2 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $types2 = array_column($rdvs2['hydra:member'] ?? $rdvs2['member'] ?? [], 'type_intervention');
        $this->assertContains('entretien', $types2);
        $this->assertNotContains('revision', $types2);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv1->getId()],
            [RendezVous::class, (int) $rdv2->getId()],
            [Client::class, (int) $client1->getId()],
            [Client::class, (int) $client2->getId()],
            [Pont::class, (int) $pont1->getId()],
            [Pont::class, (int) $pont2->getId()],
            [User::class, (int) $user1->getId()],
            [User::class, (int) $user2->getId()],
            [Atelier::class, (int) $atelier1->getId()],
            [Atelier::class, (int) $atelier2->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // PARCOURS D — Feature flag toggle + impact public
    // ════════════════════════════════════════════════════════════

    public function testFeatureFlagToggleImpactsPublicBooking(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier FF ' . $suffix)->setSlug('ff-' . $suffix)->setPlan('starter')->setActif(true);
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

        // Étape 1 : booking désactivé
        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.3.0.' . random_int(1, 254)]);
        $payloadOff = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($payloadOff['bookingEnabled']);

        // Étape 2 : admin active le flag
        $admin = $this->createUser('admin-ff-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $config->setFeatureModules([...ConfigAtelier::defaultFeatureModules(), 'public_booking' => true]);
        $em->flush();

        // Étape 3 : booking activé
        $client->request('GET', sprintf('/api/public/slots?atelier_id=%s&date_debut=%s&date_fin=%s', $atelier->getId(), $tomorrow, $tomorrow), [], [], ['REMOTE_ADDR' => '10.3.1.' . random_int(1, 254)]);
        $payloadOn = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($payloadOn['bookingEnabled']);

        $this->cleanup($em, [
            [HoraireAtelier::class, (int) $h->getId()],
            [ConfigAtelier::class, (int) $config->getId()],
            [User::class, (int) $admin->getId()],
            [Atelier::class, (int) $atelier->getId()],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // PARCOURS E — Numéros de commande : CRUD complet A→Z
    // ════════════════════════════════════════════════════════════

    public function testCommandesCrudFullWorkflow(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = (new Atelier())->setNom('Atelier CMD ' . $suffix)->setSlug('cmd-' . $suffix)->setPlan('starter')->setActif(true);
        $em->persist($atelier);
        $em->flush();

        $admin = $this->createUser('admin-cmd-' . $suffix, 'admin', (int) $atelier->getId());
        $em->persist($admin);
        $em->flush();

        $headers = array_merge($this->authHeaders($admin), ['CONTENT_TYPE' => 'application/json']);

        // 1. Créer RDV avec 2 commandes
        $client->request('POST', '/api/rendez-vous', [], [], $headers, json_encode([
            'client_nom' => 'Test',
            'client_prenom' => 'Cmd',
            'client_telephone' => '0698765432',
            'date_rdv' => (new \DateTime('+3 days'))->format('Y-m-d'),
            'heure_debut' => '09:00',
            'type_intervention' => 'revision',
            'commandes' => ['CMD-001', 'CMD-002'],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $rdv = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['CMD-001', 'CMD-002'], $rdv['commandes'] ?? []);

        // 2. Lire le RDV → commandes présentes
        $client->request('GET', '/api/rendez-vous/' . $rdv['id'], [], [], $this->authHeaders($admin));
        $read = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $readCmds = array_column($read['commandes'] ?? [], 'numero');
        $this->assertSame(['CMD-001', 'CMD-002'], $readCmds);

        // 3. Remplacer les commandes
        $client->request('POST', '/api/rendez-vous/' . $rdv['id'] . '/commandes', [], [], $headers, json_encode([
            'commandes' => ['CMD-003'],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['CMD-003'], $updated['commandes']);

        // 4. Vider les commandes
        $client->request('POST', '/api/rendez-vous/' . $rdv['id'] . '/commandes', [], [], $headers, json_encode([
            'commandes' => [],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $cleared = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEmpty($cleared['commandes']);

        $this->cleanup($em, [
            [RendezVous::class, (int) $rdv['id']],
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
