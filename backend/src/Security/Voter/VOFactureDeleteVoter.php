<?php

namespace App\Security\Voter;

use App\Entity\VOFacture;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * [C10] Interdit la suppression de toute facture VO émise.
 * Conformité Art. L.123-22 Code commerce + Art. 297 A / 256 CGI.
 * Une facture VO émise ne peut pas être supprimée — uniquement avoir.
 */
class VOFactureDeleteVoter extends Voter
{
    private const NON_DELETABLE = ['emise', 'payee', 'partiellement_payee', 'corrigee', 'annulee'];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'DELETE' && $subject instanceof VOFacture;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var VOFacture $subject */
        if (in_array($subject->getStatut(), self::NON_DELETABLE, true)) {
            return false;
        }

        return true;
    }
}
