<?php

namespace App\Service;

use App\Entity\User;

final class UserArchiveService
{
    public function archive(User $user, string $reason = 'Archive RGPD'): void
    {
        $suffix = (string) ($user->getId() ?? strtolower(bin2hex(random_bytes(6))));

        $user->setAccessStatus('archived');
        $user->setIsActive(0);
        $user->setRole('user');
        $user->setRoleMetier(null);
        $user->setAuthProvider('local');
        $user->setGoogleSub(null);
        $user->setPlainPassword(bin2hex(random_bytes(24)));
        $user->setUsername(sprintf('archive.%s', $suffix));
        $user->setEmail(sprintf('archived-user-%s@paddock.local', $suffix));
        $user->setPrenom('Compte');
        $user->setNom('Archivé');
        $user->setValidatedAt(new \DateTime());
    }
}
