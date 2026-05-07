<?php

namespace App\Security\Voter;

use App\Entity\Notification;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Vérifie qu'un utilisateur est bien destinataire d'une notification
 * (directe, broadcast, ou par rôle).
 */
class NotificationVoter extends Voter
{
    public const string VIEW = 'view';
    public const string ACKNOWLEDGE = 'acknowledge';
    public const string MARK_READ = 'mark_read';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Notification
            && in_array($attribute, [self::VIEW, self::ACKNOWLEDGE, self::MARK_READ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Notification $notification */
        $notification = $subject;

        return $this->isNotificationVisibleToUser($notification, $user);
    }

    private function isNotificationVisibleToUser(Notification $notification, User $user): bool
    {
        $userId = (int) $user->getId();
        $userRoles = $user->getRoles();

        // Directement ciblée
        if ($notification->getTargetUserId() === $userId) {
            return true;
        }

        // Broadcast (aucune cible spécifique)
        if ($notification->getTargetUserId() === null && $notification->getTargetRole() === null) {
            return true;
        }

        // Correspondance par rôle (targetRole string)
        if ($notification->getTargetRole() !== null && in_array($notification->getTargetRole(), $userRoles, true)) {
            return true;
        }

        return false;
    }
}
