<?php

namespace App\Security\Voter;

use App\Entity\Facture;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * [C10] Interdit la suppression de toute facture émise.
 * Conformité Art. L.123-22 Code commerce — conservation 10 ans.
 * Une facture émise ne peut être annulée que par avoir.
 */
class FactureDeleteVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'DELETE' && $subject instanceof Facture;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Facture $subject */
        $statut = $subject->getStatut();

        // Jamais de suppression sur une facture émise ou dans un statut avancé
        $nonDeletableStatuts = [
            Facture::STATUS_EMISE,
            Facture::STATUS_PAYEE,
            Facture::STATUS_PARTIELLEMENT_PAYEE,
            Facture::STATUS_CORRIGEE,
        ];

        if (in_array($statut, $nonDeletableStatuts, true)) {
            return false;
        }

        return true;
    }
}
