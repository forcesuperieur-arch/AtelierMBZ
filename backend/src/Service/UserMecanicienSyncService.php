<?php

namespace App\Service;

use App\Entity\Mecanicien;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserMecanicienSyncService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $currentAtelierResolver,
    ) {}

    public function syncForUser(User $user): ?Mecanicien
    {
        $userId = $user->getId();
        if ($userId === null) {
            return null;
        }

        /** @var Mecanicien|null $mecanicien */
        $mecanicien = $this->em->getRepository(Mecanicien::class)->findOneBy(['userId' => $userId]);

        if (!$this->shouldHaveLinkedMecanicien($user)) {
            if ($mecanicien instanceof Mecanicien) {
                $mecanicien->setIsActive(0);
            }

            return $mecanicien;
        }

        if (!$mecanicien instanceof Mecanicien) {
            $mecanicien = new Mecanicien();
            $mecanicien->setUserId($userId);
            $this->em->persist($mecanicien);
        }

        [$prenom, $nom] = $this->resolveIdentity($user, $mecanicien);

        $atelierId = $user->getAtelierId() ?? $this->currentAtelierResolver->resolveAtelierId();

        $mecanicien
            ->setAtelierId($atelierId)
            ->setPrenom($prenom)
            ->setNom($nom)
            ->setIsActive($user->getIsActive());

        return $mecanicien;
    }

    private function shouldHaveLinkedMecanicien(User $user): bool
    {
        if ($user->getAccessStatus() === 'archived') {
            return false;
        }

        if ($user->getRole() === 'mecanicien') {
            return true;
        }

        return $user->getRoleMetier()?->getCode() === 'mecanicien';
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveIdentity(User $user, ?Mecanicien $mecanicien): array
    {
        $prenom = trim((string) ($user->getPrenom() ?? ''));
        $nom = trim((string) ($user->getNom() ?? ''));

        $username = trim($user->getUsername());
        $parts = preg_split('/[._\-\s]+/', $username) ?: [];

        $fallbackPrenom = $prenom !== ''
            ? $prenom
            : ($mecanicien?->getPrenom() ?: ucfirst(strtolower($parts[0] ?? 'Compte')));

        $fallbackNom = $nom !== ''
            ? $nom
            : ($mecanicien?->getNom() ?: strtoupper(trim(implode(' ', array_slice($parts, 1))) ?: ($username !== '' ? $username : 'MECANICIEN')));

        return [$fallbackPrenom, $fallbackNom];
    }
}
