<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class NotificationControllerTest extends WebTestCase
{
    public function testUserCannotSeeOtherUserDirectNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());
        $userB = $this->createUser($em, 'user-b-' . $suffix, 'mecanicien', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('GET', '/api/notifications?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userB));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $titles = array_column($payload['items'] ?? [], 'title');
            $this->assertNotContains('Privée A ' . $suffix, $titles);
        } finally {
            $this->cleanup($em, [$notif, $userA, $userB, $atelier]);
        }
    }

    public function testUserCanSeeBroadcastNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());
        $userB = $this->createUser($em, 'user-b-' . $suffix, 'mecanicien', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Broadcast ' . $suffix)
            ->setMessage('Message broadcast');
        $em->persist($notif);
        $em->flush();

        try {
            foreach ([$userA, $userB] as $user) {
                $client->request('GET', '/api/notifications?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($user));
                $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
                $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $titles = array_column($payload['items'] ?? [], 'title');
                $this->assertContains('Broadcast ' . $suffix, $titles);
            }
        } finally {
            $this->cleanup($em, [$notif, $userA, $userB, $atelier]);
        }
    }

    public function testUserCanSeeRoleMatchedNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $admin = $this->createUser($em, 'admin-' . $suffix, 'admin', (int) $atelier->getId());
        $service = $this->createUser($em, 'service-' . $suffix, 'service_client', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Admin only ' . $suffix)
            ->setMessage('Message admin')
            ->setTargetRole('ROLE_ADMIN');
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('GET', '/api/notifications?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($admin));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $titles = array_column($payload['items'] ?? [], 'title');
            $this->assertContains('Admin only ' . $suffix, $titles);

            $client->request('GET', '/api/notifications?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($service));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $titles = array_column($payload['items'] ?? [], 'title');
            $this->assertNotContains('Admin only ' . $suffix, $titles);
        } finally {
            $this->cleanup($em, [$notif, $admin, $service, $atelier]);
        }
    }

    public function testUnreadCountExcludesOtherUserNotifications(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());
        $userB = $this->createUser($em, 'user-b-' . $suffix, 'mecanicien', (int) $atelier->getId());

        $notifA = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $notifB = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Broadcast ' . $suffix)
            ->setMessage('Message broadcast');
        $em->persist($notifA);
        $em->persist($notifB);
        $em->flush();

        try {
            $client->request('GET', '/api/notifications/unread-count?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userB));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            // userB ne doit voir que le broadcast
            $this->assertSame(1, $payload['count'] ?? null);
        } finally {
            $this->cleanup($em, [$notifA, $notifB, $userA, $userB, $atelier]);
        }
    }

    public function testUserCannotAcknowledgeOtherUserNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());
        $userB = $this->createUser($em, 'user-b-' . $suffix, 'mecanicien', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('POST', '/api/notifications/' . $notif->getId() . '/acknowledge?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userB));
            $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        } finally {
            $this->cleanup($em, [$notif, $userA, $userB, $atelier]);
        }
    }

    public function testUserCannotMarkReadOtherUserNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());
        $userB = $this->createUser($em, 'user-b-' . $suffix, 'mecanicien', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('POST', '/api/notifications/' . $notif->getId() . '/mark-read?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userB));
            $this->assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
        } finally {
            $this->cleanup($em, [$notif, $userA, $userB, $atelier]);
        }
    }

    public function testUserCanAcknowledgeOwnNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('POST', '/api/notifications/' . $notif->getId() . '/acknowledge?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userA));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue($payload['acknowledged'] ?? false);
        } finally {
            $this->cleanup($em, [$notif, $userA, $atelier]);
        }
    }

    public function testUserCanMarkReadOwnNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelier = $this->createAtelier($em, $suffix);
        $userA = $this->createUser($em, 'user-a-' . $suffix, 'service_client', (int) $atelier->getId());

        $notif = (new Notification())
            ->setAtelierId((int) $atelier->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Privée A ' . $suffix)
            ->setMessage('Message privé')
            ->setTargetUserId((int) $userA->getId());
        $em->persist($notif);
        $em->flush();

        try {
            $client->request('POST', '/api/notifications/' . $notif->getId() . '/mark-read?atelier_id=' . $atelier->getId(), [], [], $this->authHeaders($userA));
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertTrue($payload['read'] ?? false);
        } finally {
            $this->cleanup($em, [$notif, $userA, $atelier]);
        }
    }

    private function createAtelier(EntityManagerInterface $em, string $suffix): Atelier
    {
        $atelier = (new Atelier())
            ->setNom('Atelier ' . $suffix)
            ->setSlug('atelier-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $em->persist($atelier);
        $em->flush();

        return $atelier;
    }

    private function createUser(EntityManagerInterface $em, string $username, string $role, int $atelierId): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($username . '@example.test')
            ->setHashedPassword('test')
            ->setRole($role)
            ->setAtelierId($atelierId);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * @param array<object> $entities
     */
    private function cleanup(EntityManagerInterface $em, array $entities): void
    {
        foreach (array_reverse($entities) as $entity) {
            if ($em->contains($entity)) {
                $em->remove($entity);
            } else {
                $refreshed = $em->find($entity::class, $entity->getId());
                if ($refreshed !== null) {
                    $em->remove($refreshed);
                }
            }
        }
        $em->flush();
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
}
