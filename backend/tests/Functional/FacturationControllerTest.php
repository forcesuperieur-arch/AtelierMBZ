<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FacturationControllerTest extends WebTestCase
{
    public function testFacturationListIsScopedAndAvoirRefundsAreJournaled(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $fixture = $this->createFacturationFixture($em);

        $client->request('GET', '/api/facturation', [], [], $this->authHeaders($fixture['user']));

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $numbers = array_column($payload, 'numero_facture');

        $this->assertContains($fixture['facture']->getNumeroFacture(), $numbers);
        $this->assertNotContains($fixture['foreignFacture']->getNumeroFacture(), $numbers);

        $client->request(
            'POST',
            '/api/facturation/' . $fixture['facture']->getId() . '/paiement',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'mode_paiement' => 'carte_bancaire',
                'montant' => '40.00',
                'reference' => 'CB-TEST-001',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $paymentPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(Facture::STATUS_PARTIELLEMENT_PAYEE, $paymentPayload['statut'] ?? null);
        $this->assertSame('40.00', $paymentPayload['total_paye'] ?? null);
        $this->assertSame('80.00', $paymentPayload['reste_a_payer'] ?? null);

        $client->request(
            'POST',
            '/api/facturation/' . $fixture['facture']->getId() . '/avoir',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode(['motif' => 'Erreur de saisie sur le ticket atelier'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $avoirPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $avoirId = (int) ($avoirPayload['id'] ?? 0);
        $this->assertGreaterThan(0, $avoirId);

        $client->request(
            'POST',
            '/api/facturation/' . $avoirId . '/remboursement',
            [],
            [],
            $this->authHeaders($fixture['user']),
            json_encode([
                'mode_paiement' => 'virement',
                'montant' => '50.00',
                'reference' => 'VRM-TEST-001',
                'notes' => 'Premier remboursement client',
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $refundPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(Facture::STATUS_PARTIELLEMENT_PAYEE, $refundPayload['statut'] ?? null);
        $this->assertSame('50.00', $refundPayload['total_paye'] ?? null);
        $this->assertSame('70.00', $refundPayload['reste_a_payer'] ?? null);

        $em->clear();

        $avoir = $em->getRepository(Facture::class)->find($avoirId);
        $this->assertNotNull($avoir);
        $this->assertTrue($avoir->isAvoir());
        $this->assertSame('50.00', $avoir->getMontantPaye());
        $this->assertSame('70.00', $avoir->getResteAPayer());
        $this->assertCount(1, $avoir->getPaiements());
        $this->assertSame('remboursement', $avoir->getPaiements()->first()->getTypeOperation());
    }

    /**
     * @return array{user: User, facture: Facture, foreignFacture: Facture}
     */
    private function createFacturationFixture(EntityManagerInterface $em): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('compta-' . $suffix)
            ->setEmail(sprintf('compta-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('comptable')
            ->setAtelierId(1);

        $clientAtelier1 = (new Client())
            ->setAtelierId(1)
            ->setNom('Dupont-' . $suffix)
            ->setPrenom('Jean')
            ->setTelephone('0600000101')
            ->setEmail(sprintf('dupont-%s@example.test', $suffix));

        $vehiculeAtelier1 = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($clientAtelier1)
            ->setPlaque('FA-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('BMW')
            ->setModele('F900R');

        $rdvAtelier1 = (new RendezVous())
            ->setAtelierId(1)
            ->setClient($clientAtelier1)
            ->setVehicule($vehiculeAtelier1)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('08:30:00'))
            ->setTypeIntervention('Révision')
            ->setStatut('facture');

        $facture = (new Facture())
            ->setNumeroFacture('FAC-TEST-' . strtoupper($suffix))
            ->setRendezVous($rdvAtelier1)
            ->setClient($clientAtelier1)
            ->setVehicule($vehiculeAtelier1)
            ->setAtelierId(1)
            ->setNature(Facture::NATURE_FACTURE)
            ->setTotalMoHt('100.00')
            ->setTotalPiecesHt('0.00')
            ->setTotalHt('100.00')
            ->setTvaMo('20.00')
            ->setTvaPieces('0.00')
            ->setTotalTva('20.00')
            ->setTotalTtc('120.00')
            ->setTauxHoraire('65.00')
            ->setTvaMoTaux(20.0)
            ->setTvaPiecesTaux(20.0)
            ->setStatut(Facture::STATUS_EMISE);

        $clientAtelier2 = (new Client())
            ->setAtelierId(2)
            ->setNom('Martin-' . $suffix)
            ->setPrenom('Claire')
            ->setTelephone('0600000202')
            ->setEmail(sprintf('martin-%s@example.test', $suffix));

        $vehiculeAtelier2 = (new Vehicule())
            ->setAtelierId(2)
            ->setClient($clientAtelier2)
            ->setPlaque('FB-' . strtoupper(substr(strrev($suffix), 0, 4)))
            ->setMarque('Honda')
            ->setModele('CB650R');

        $rdvAtelier2 = (new RendezVous())
            ->setAtelierId(2)
            ->setClient($clientAtelier2)
            ->setVehicule($vehiculeAtelier2)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('11:00:00'))
            ->setTypeIntervention('Diagnostic')
            ->setStatut('facture');

        $foreignFacture = (new Facture())
            ->setNumeroFacture('FAC-FOREIGN-' . strtoupper($suffix))
            ->setRendezVous($rdvAtelier2)
            ->setClient($clientAtelier2)
            ->setVehicule($vehiculeAtelier2)
            ->setAtelierId(2)
            ->setNature(Facture::NATURE_FACTURE)
            ->setTotalMoHt('50.00')
            ->setTotalPiecesHt('0.00')
            ->setTotalHt('50.00')
            ->setTvaMo('10.00')
            ->setTvaPieces('0.00')
            ->setTotalTva('10.00')
            ->setTotalTtc('60.00')
            ->setTauxHoraire('65.00')
            ->setTvaMoTaux(20.0)
            ->setTvaPiecesTaux(20.0)
            ->setStatut(Facture::STATUS_EMISE);

        $em->persist($user);
        $em->persist($clientAtelier1);
        $em->persist($vehiculeAtelier1);
        $em->persist($rdvAtelier1);
        $em->persist($facture);
        $em->persist($clientAtelier2);
        $em->persist($vehiculeAtelier2);
        $em->persist($rdvAtelier2);
        $em->persist($foreignFacture);
        $em->flush();

        return [
            'user' => $user,
            'facture' => $facture,
            'foreignFacture' => $foreignFacture,
        ];
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
}