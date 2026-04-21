<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Mecanicien;
use App\Entity\OrdreReparation;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MecanicienControllerTest extends WebTestCase
{
    public function testMeEndpointReturnsHelpfulNotFoundWhenMechanicNotLinked(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $user = (new User())
            ->setUsername('meca-unlinked-' . $suffix)
            ->setEmail(sprintf('meca-unlinked-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('mecanicien')
            ->setAtelierId(1);

        $em->persist($user);
        $em->flush();

        $client->request('GET', '/api/mecanicien/me', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('MECANICIEN_NOT_LINKED', $payload['code'] ?? null);
    }

    public function testSaveRapportStoresMechanicFieldsWithoutOverwritingRdvCommentaire(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, [
            'statut' => 'reception',
            'commentaire' => 'Bruit moteur au démarrage',
        ]);

        $client->request(
            'PATCH',
            '/api/mecanicien/me/rapport/' . $fixture['ordre']->getId(),
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'mechanic_notes' => 'Chaîne réglée, contrôle routier à prévoir.',
                'mechanic_checkup' => [
                    'freins' => 'ok',
                    'eclairage' => 'nok',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $em->clear();
        $savedRdv = $em->getRepository(RendezVous::class)->find($fixture['rdv']->getId());
        $savedOrdre = $em->getRepository(OrdreReparation::class)->find($fixture['ordre']->getId());

        $this->assertSame('Bruit moteur au démarrage', $savedRdv->getCommentaire());
        $this->assertSame('Chaîne réglée, contrôle routier à prévoir.', $savedOrdre->getMechanicNotes());
        $this->assertSame([
            'freins' => 'ok',
            'eclairage' => 'nok',
        ], $savedOrdre->getMechanicCheckup());

        $legacyState = json_decode((string) $savedOrdre->getEtatVehicule(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Chaîne réglée, contrôle routier à prévoir.', $legacyState['mechanic_notes'] ?? null);
        $this->assertSame('ok', $legacyState['mechanic_checkup']['freins'] ?? null);
    }

    public function testTerminerRequiresValidatedEssaiRoutier(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, [
            'statut' => 'en_cours',
            'commentaire' => 'Vérification train avant',
            'kilometrage' => 12000,
        ]);
        $this->addAfterWorkPhotos($em, $fixture['rdv']);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ESSAI_ROUTIER_REQUIS', $payload['code'] ?? null);

        $client->request(
            'POST',
            '/api/mecanicien/me/essai-routier',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'rdv_id' => $fixture['rdv']->getId(),
                'km_debut' => 12000,
                'km_fin' => 12005,
                'dureeMinutes' => 12,
                'checkpoints' => [
                    ['key' => 'demarrage', 'statut' => 'ok'],
                    ['key' => 'acceleration', 'statut' => 'ok'],
                    ['key' => 'freinage', 'statut' => 'ok'],
                    ['key' => 'virage_gauche', 'statut' => 'ok'],
                    ['key' => 'virage_droit', 'statut' => 'ok'],
                    ['key' => 'tenue_route', 'statut' => 'ok'],
                    ['key' => 'bruit_moteur', 'statut' => 'ok'],
                    ['key' => 'bruit_freins', 'statut' => 'ok'],
                    ['key' => 'vibrations', 'statut' => 'ok'],
                    ['key' => 'comportement_general', 'statut' => 'ok'],
                ],
                'valider' => true,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $roadTestPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($roadTestPayload['valide'] ?? false));
        $this->assertSame('valide', $roadTestPayload['statut'] ?? null);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $transitionPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('RAPPORT_INTERVENTION_INCOMPLET', $transitionPayload['code'] ?? null);

        $rapportId = (int) ($transitionPayload['rapport_id'] ?? 0);
        $this->assertGreaterThan(0, $rapportId);

        $this->completeAndSignRapport($client, $fixture['user'], $fixture['rdv']->getId(), $rapportId, 12005);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $transitionPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('termine', $transitionPayload['statut'] ?? null);
    }

    public function testAnomalyRoadTestRequiresMechanicNotesBeforeTerminer(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, [
            'statut' => 'en_cours',
            'commentaire' => 'Contrôle freinage',
            'kilometrage' => 34000,
        ]);
        $this->addAfterWorkPhotos($em, $fixture['rdv']);

        $client->request(
            'POST',
            '/api/mecanicien/me/essai-routier',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'rdv_id' => $fixture['rdv']->getId(),
                'km_debut' => 34000,
                'km_fin' => 34008,
                'dureeMinutes' => 14,
                'checkpoints' => [
                    ['key' => 'demarrage', 'statut' => 'ok'],
                    ['key' => 'acceleration', 'statut' => 'ok'],
                    ['key' => 'freinage', 'statut' => 'nok'],
                    ['key' => 'virage_gauche', 'statut' => 'ok'],
                    ['key' => 'virage_droit', 'statut' => 'ok'],
                    ['key' => 'tenue_route', 'statut' => 'ok'],
                    ['key' => 'bruit_moteur', 'statut' => 'ok'],
                    ['key' => 'bruit_freins', 'statut' => 'nok'],
                    ['key' => 'vibrations', 'statut' => 'ok'],
                    ['key' => 'comportement_general', 'statut' => 'ok'],
                ],
                'actionsCorrectives' => 'Purge du circuit et contrôle complémentaire du freinage préconisés.',
                'valider' => true,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $roadTestPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) ($roadTestPayload['valide'] ?? false));
        $this->assertSame('anomalie_detectee', $roadTestPayload['statut'] ?? null);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('MECHANIC_NOTES_REQUIRED', $payload['code'] ?? null);

        $client->request(
            'PATCH',
            '/api/mecanicien/me/rapport/' . $fixture['ordre']->getId(),
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'mechanic_notes' => 'Essai routier NOK sur le freinage, purge et contrôle supplémentaire requis.',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $transitionPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('RAPPORT_INTERVENTION_INCOMPLET', $transitionPayload['code'] ?? null);

        $rapportId = (int) ($transitionPayload['rapport_id'] ?? 0);
        $this->assertGreaterThan(0, $rapportId);

        $this->completeAndSignRapport($client, $fixture['user'], $fixture['rdv']->getId(), $rapportId, 34008);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/terminer',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testMechanicCanPauseWaitForPartsAndResumeIntervention(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createMechanicFixture($em, [
            'statut' => 'en_cours',
            'commentaire' => 'Remplacement kit chaîne',
        ]);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/mettre_en_pause',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_pause', $payload['statut'] ?? null);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/mettre_en_attente_pieces',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_attente_pieces', $payload['statut'] ?? null);

        $client->request(
            'POST',
            '/api/rendez-vous/' . $fixture['rdv']->getId() . '/transition/reprendre_apres_pieces',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en_cours', $payload['statut'] ?? null);
    }

    private function fetchDraftRapportId(KernelBrowser $client, User $user, int $rdvId): int
    {
        $client->request('GET', '/api/rdv/' . $rdvId . '/rapport', [], [], $this->authHeaders($user));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (int) ($payload['id'] ?? 0);
    }

    private function completeAndSignRapport(KernelBrowser $client, User $user, int $rdvId, int $rapportId, int $kilometrageRestitution): void
    {
        if ($rapportId <= 0) {
            $rapportId = $this->fetchDraftRapportId($client, $user, $rdvId);
        }

        $client->request(
            'PUT',
            '/api/rapport/' . $rapportId,
            [],
            [],
            $this->authHeaders($user),
            json_encode([
                'travauxRealises' => 'Intervention terminée, contrôles finaux effectués et essai routier validé.',
                'recommandations' => 'Contrôle de routine à la prochaine échéance atelier.',
                'kilometrageRestitution' => $kilometrageRestitution,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(
            'POST',
            '/api/rapport/' . $rapportId . '/sign-mecanicien',
            [],
            [],
            $this->authHeaders($user),
            json_encode(['signature' => $this->sampleSignatureDataUrl()], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    /**
     * @return array{user: User, mecanicien: Mecanicien, rdv: RendezVous, ordre: OrdreReparation}
     */
    private function createMechanicFixture(EntityManagerInterface $em, array $rdvOverrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('meca-' . $suffix)
            ->setEmail(sprintf('meca-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('mecanicien')
            ->setAtelierId(1);
        $em->persist($user);
        $em->flush();

        $mecanicien = (new Mecanicien())
            ->setAtelierId(1)
            ->setNom('Mechanic')
            ->setPrenom('Test')
            ->setCouleur('#123456')
            ->setIsActive(1)
            ->setUserId($user->getId());

        $client = (new Client())
            ->setAtelierId(1)
            ->setNom('Client-' . $suffix)
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('client-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($client)
            ->setPlaque('MC-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('BMW')
            ->setModele('R1250GS')
            ->setTypeMoto('Trail');

        $rdv = (new RendezVous())
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setAtelierId(1)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('09:00:00'))
            ->setTypeIntervention('Diagnostic')
            ->setCommentaire((string) ($rdvOverrides['commentaire'] ?? 'Problème client'))
            ->setStatut((string) ($rdvOverrides['statut'] ?? 'reception'))
            ->setKilometrage((int) ($rdvOverrides['kilometrage'] ?? 15000))
            ->setMecanicien($mecanicien);

        $ordre = (new OrdreReparation())
            ->setRendezVous($rdv)
            ->setNumeroOr('OR-' . $suffix)
            ->setTypeOr('initial')
            ->setStatut('signe')
            ->setSignatureClient('signed-data');

        $em->persist($mecanicien);
        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->persist($ordre);
        $em->flush();

        return [
            'user' => $user,
            'mecanicien' => $mecanicien,
            'rdv' => $rdv,
            'ordre' => $ordre,
        ];
    }

    private function addAfterWorkPhotos(EntityManagerInterface $em, RendezVous $rdv, int $count = 2): void
    {
        for ($i = 0; $i < $count; $i++) {
            $photo = (new PhotoIntervention())
                ->setRendezVous($rdv)
                ->setAtelierId($rdv->getAtelierId())
                ->setFilename(sprintf('after-work-%d-%s.jpg', $rdv->getId(), $i))
                ->setOriginalName(sprintf('after-work-%d.jpg', $i))
                ->setType('apres_travaux')
                ->setDescription('Photo après travaux ' . $i);

            $em->persist($photo);
        }

        $em->flush();
    }

    /**
     * @return array{CONTENT_TYPE: string, HTTP_AUTHORIZATION: string}
     */
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user),
        ];
    }

    private function sampleSignatureDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0FYAAAAASUVORK5CYII=';
    }
}