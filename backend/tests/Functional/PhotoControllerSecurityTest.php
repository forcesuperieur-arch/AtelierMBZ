<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PhotoControllerSecurityTest extends WebTestCase
{
    private function createUser(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $user = (new User())
            ->setUsername('photo-' . $suffix)
            ->setEmail(sprintf('photo-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('admin')
            ->setAtelierId(1);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createRdvFixture(EntityManagerInterface $em): RendezVous
    {
        $suffix = bin2hex(random_bytes(4));

        $client = (new Client())
            ->setAtelierId(1)
            ->setNom('Photo-' . $suffix)
            ->setPrenom('Test')
            ->setTelephone('0600000000')
            ->setEmail(sprintf('photo-client-%s@example.test', $suffix));

        $vehicule = (new Vehicule())
            ->setAtelierId(1)
            ->setClient($client)
            ->setPlaque('PH-' . strtoupper(substr($suffix, 0, 4)))
            ->setMarque('Honda')
            ->setModele('CBR650R');

        $rdv = (new RendezVous())
            ->setAtelierId(1)
            ->setClient($client)
            ->setVehicule($vehicule)
            ->setDateRdv(new \DateTime('today'))
            ->setHeureRdv(new \DateTime('09:00:00'))
            ->setTypeIntervention('Révision');

        $em->persist($client);
        $em->persist($vehicule);
        $em->persist($rdv);
        $em->flush();

        return $rdv;
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

    public function testServeRejectsPathTraversal(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        // Encoded traversal attempt: Symfony decodes to ../../.env,
        // basename strips it to .env, and realpath resolves within var/photos.
        $client->request(
            'GET',
            '/api/photos/file/..',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('File not found', $payload['error'] ?? null);
    }

    public function testServeReturns404ForMissingFile(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        $client->request(
            'GET',
            '/api/photos/file/nonexistent-file-12345.jpg',
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('File not found', $payload['error'] ?? null);
    }

    public function testServeRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/photos/file/test.jpg');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testServeReturnsFileForExistingPhoto(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createUser();
        $rdv = $this->createRdvFixture($em);

        $uploadDir = static::getContainer()->getParameter('kernel.project_dir') . '/var/photos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'test-photo-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($uploadDir . '/' . $filename, 'fake-image-content');

        $photo = (new PhotoIntervention())
            ->setRendezVous($rdv)
            ->setFilename($filename)
            ->setOriginalName('original.jpg')
            ->setType('en_cours')
            ->setAtelierId(1);

        $em->persist($photo);
        $em->flush();

        $client->request(
            'GET',
            '/api/photos/file/' . $filename,
            [],
            [],
            $this->authHeaders($user)
        );

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Cleanup
        @unlink($uploadDir . '/' . $filename);
    }
}
