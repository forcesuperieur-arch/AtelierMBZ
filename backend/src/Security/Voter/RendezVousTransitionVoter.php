<?php

namespace App\Security\Voter;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RendezVousTransitionVoter extends Voter
{
    /** @var string[] */
    private const RECEPTION_TRANSITIONS = [
        'reception',
        'confirmer',
        'reserver',
        'declarer_no_show',
        'no_show',
        'reporter',
        'mettre_en_gardiennage',
        'passer_gardiennage',
        'sortir_gardiennage',
        'restituer',
        'restituer_partiel',
        'annuler',
    ];

    /** @var string[] */
    private const MECANICIEN_TRANSITIONS = [
        'start_travail',
        'mettre_en_pause',
        'reprendre',
        'mettre_en_attente_pieces',
        'reprendre_apres_pieces',
        'mettre_en_attente_reprise',
        'reprendre_demain',
        'terminer',
    ];

    /** @var string[] */
    private const COMPTABLE_TRANSITIONS = [
        'facturer',
        'payer',
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof RendezVous) {
            return false;
        }

        return \in_array($attribute, self::RECEPTION_TRANSITIONS, true)
            || \in_array($attribute, self::MECANICIEN_TRANSITIONS, true)
            || \in_array($attribute, self::COMPTABLE_TRANSITIONS, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        if (\in_array($attribute, self::RECEPTION_TRANSITIONS, true)) {
            return \in_array('ROLE_RECEPTIONNAIRE', $roles, true);
        }

        if (\in_array($attribute, self::COMPTABLE_TRANSITIONS, true)) {
            return \in_array('ROLE_COMPTABLE', $roles, true);
        }

        if (\in_array($attribute, self::MECANICIEN_TRANSITIONS, true)) {
            if (!\in_array('ROLE_MECANICIEN', $roles, true)) {
                return false;
            }

            /** @var RendezVous $rdv */
            $rdv = $subject;
            $mecanicien = $rdv->getMecanicien();

            if ($mecanicien === null) {
                return false;
            }

            return $mecanicien->getUserId() === $user->getId();
        }

        return false;
    }
}
