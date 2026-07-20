<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RendezVous;

/**
 * Règle UNIQUE d'expiration des liens publics tokenisés (suivi, restitution,
 * photos publiques) : le token reste valide tant que le RDV n'est pas dans un
 * statut terminal, puis expire 30 jours après la date du RDV (RGPD).
 *
 * Mutualisée entre PublicSuiviController et PublicPhotoController — ne jamais
 * réintroduire de règle locale divergente (bug historique : les photos
 * expiraient à createdAt+30j pendant que le lien de suivi restait valide,
 * cassant la galerie EDL et le comparatif de restitution au moment du litige).
 */
class PublicTokenPolicy
{
    /**
     * Statuts terminaux du RDV au sens des liens publics. DOIT rester en miroir
     * des places « de fin de vie » de config/packages/workflow.yaml : un dossier
     * facturé/payé/restitué (total ou partiel) est clôturé — sinon isTokenExpired()
     * renvoie false pour toujours et le lien public (PII, photos, EDL) ne périme
     * JAMAIS. `livre` est conservé par compat (place absente du workflow actuel).
     */
    public const STATUTS_TERMINAUX = [
        'termine', 'annule', 'restitue', 'restitue_partiel', 'facture', 'paye', 'livre',
    ];

    /** Délai de grâce après clôture, décompté depuis la date du RDV. */
    private const GRACE_PERIOD = '+30 days';

    public function isTokenExpired(RendezVous $rdv): bool
    {
        if (!in_array($rdv->getStatut(), self::STATUTS_TERMINAUX, true)) {
            return false;
        }

        $dateRdv = $rdv->getDateRdv();
        if (!$dateRdv) {
            return false;
        }

        return new \DateTime() > (clone $dateRdv)->modify(self::GRACE_PERIOD);
    }
}
