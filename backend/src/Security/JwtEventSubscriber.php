<?php
namespace App\Security;

use App\Entity\RevokedToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Handles JWT events: enriches payload on creation, checks revocation on decode.
 */
class JwtEventSubscriber
{
    public function __construct(private EntityManagerInterface $em) {}

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        $payload['user_id'] = $user->getId();
        $payload['atelier_id'] = $user->getAtelierId();
        $payload['role'] = $user->getRole();
        $payload['jti'] = bin2hex(random_bytes(16));

        $event->setData($payload);
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $jti = $payload['jti'] ?? null;

        if (!$jti) {
            $event->markAsInvalid();
            return;
        }

        $revoked = $this->em->getRepository(RevokedToken::class)->findOneBy(['jti' => $jti]);
        if ($revoked) {
            $event->markAsInvalid();
        }
    }
}
