<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\BookingAtelierAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lot C3 : le SRC prend un RDV au téléphone pour le compte d'un client, dans l'atelier de SON
 * CHOIX (contrairement au staff normal, cantonné à son propre atelier) — origine tracée
 * src_telephone pour le KPI par canal.
 */
class SrcBookingProxyTest extends WebTestCase
{
    private function authHeaders(User $user): array
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtManager->create($user)];
    }

    public function testServiceClientCanBookIntoAnotherAtelierWithSrcTelephoneOrigin(): void
    {
        $webClient = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierSrc = (new Atelier())->setNom('SrcHome' . $suffix)->setSlug('src-home-' . $suffix)->setActif(true);
        $atelierCible = (new Atelier())->setNom('SrcCible' . $suffix)->setSlug('src-cible-' . $suffix)->setActif(true);
        $em->persist($atelierSrc);
        $em->persist($atelierCible);
        $em->flush();

        $config = new ConfigAtelier();
        $config->setAtelierId($atelierCible->getId());
        $modules = ConfigAtelier::defaultFeatureModules();
        $modules[BookingAtelierAccessService::FEATURE_RDV_SIEGE] = true;
        $config->setFeatureModules($modules);
        $em->persist($config);
        $em->flush();

        $src = (new User())->setUsername('srcbooking-' . $suffix)->setEmail("srcbooking-$suffix@example.test")->setHashedPassword('x')->setRole('service_client')->setAtelierId($atelierSrc->getId());
        $em->persist($src);
        $em->flush();
        $em->clear();

        $webClient->request(
            'POST',
            '/api/rendez-vous',
            [],
            [],
            $this->authHeaders($src),
            json_encode([
                'client_nom' => 'Proxy',
                'client_prenom' => 'Test' . $suffix,
                'client_telephone' => '0699887766',
                'date_rdv' => (new \DateTime('+2 days'))->format('Y-m-d'),
                'heure_rdv' => '10:00',
                'type_intervention' => 'Révision',
                'origine' => 'src_telephone',
                'atelier_id' => $atelierCible->getId(),
            ]),
        );

        $this->assertSame(Response::HTTP_CREATED, $webClient->getResponse()->getStatusCode());
        $payload = json_decode($webClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $rdv = $em->getRepository(RendezVous::class)->find($payload['id']);
        $this->assertNotNull($rdv);
        $this->assertSame('src_telephone', $rdv->getOrigine());
        $this->assertSame($atelierCible->getId(), $rdv->getAtelierId(), 'le RDV doit être créé dans l\'atelier CHOISI par le SRC, pas son atelier d\'origine');
    }
}
