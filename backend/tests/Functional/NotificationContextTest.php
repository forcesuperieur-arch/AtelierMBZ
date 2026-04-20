<?php

namespace App\Tests\Functional;

use App\Entity\Atelier;
use App\Entity\ConfigAtelier;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class NotificationContextTest extends WebTestCase
{
    public function testServiceClientCanListNotificationsForSelectedAtelierContext(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));

        $atelierA = (new Atelier())
            ->setNom('Notif A ' . $suffix)
            ->setSlug('notif-a-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $atelierB = (new Atelier())
            ->setNom('Notif B ' . $suffix)
            ->setSlug('notif-b-' . $suffix)
            ->setPlan('starter')
            ->setActif(true);
        $em->persist($atelierA);
        $em->persist($atelierB);
        $em->flush();

        $configB = (new ConfigAtelier())
            ->setAtelierId((int) $atelierB->getId())
            ->setFeatureModules([
                ...ConfigAtelier::defaultFeatureModules(),
                'rdv_siege' => true,
            ]);
        $em->persist($configB);

        $user = (new User())
            ->setUsername('notif-service-' . $suffix)
            ->setEmail(sprintf('notif-service-%s@example.test', $suffix))
            ->setHashedPassword('test')
            ->setRole('service_client')
            ->setAtelierId((int) $atelierA->getId());
        $em->persist($user);

        $notifA = (new Notification())
            ->setAtelierId((int) $atelierA->getId())
            ->setType('test')
            ->setSeverity('info')
            ->setTitle('Notif atelier A ' . $suffix)
            ->setMessage('Message A')
            ->setTargetRoles(['ROLE_SERVICE_CLIENT']);
        $notifB = (new Notification())
            ->setAtelierId((int) $atelierB->getId())
            ->setType('test')
            ->setSeverity('warning')
            ->setTitle('Notif atelier B ' . $suffix)
            ->setMessage('Message B')
            ->setTargetRoles(['ROLE_SERVICE_CLIENT']);

        $em->persist($notifA);
        $em->persist($notifB);
        $em->flush();

        try {
            $client->request('GET', '/api/notifications?atelier_id=' . $atelierB->getId(), [], [], $this->authHeaders($user));

            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
            $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $titles = array_values(array_column($payload['items'] ?? [], 'title'));

            $this->assertContains('Notif atelier B ' . $suffix, $titles);
            $this->assertNotContains('Notif atelier A ' . $suffix, $titles);
        } finally {
            foreach ([$notifA, $notifB, $user, $configB, $atelierA, $atelierB] as $entity) {
                $managed = $em->contains($entity) ? $entity : $em->merge($entity);
                $em->remove($managed);
            }
            $em->flush();
        }
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
