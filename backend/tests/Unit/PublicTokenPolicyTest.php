<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\RendezVous;
use App\Service\PublicTokenPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille la règle d'expiration des liens publics : un dossier CLÔTURÉ
 * (y compris facturé/payé/restitué partiel) doit expirer 30 j après la date
 * du RDV. Régression corrigée : facture/paye étaient absents de STATUTS_TERMINAUX
 * → le lien public (PII, photos, EDL) ne périmait jamais sur ces dossiers.
 */
class PublicTokenPolicyTest extends TestCase
{
    private function rdv(string $statut, string $dateRdv): RendezVous
    {
        $rdv = new RendezVous();
        $rdv->setStatut($statut);
        $rdv->setDateRdv(new \DateTime($dateRdv));

        return $rdv;
    }

    public function testNonTerminalStatusNeverExpiresEvenWithOldDate(): void
    {
        $policy = new PublicTokenPolicy();
        // Dossier encore ouvert : le lien reste valide quelle que soit l'ancienneté.
        $this->assertFalse($policy->isTokenExpired($this->rdv('en_cours', '-2 years')));
        $this->assertFalse($policy->isTokenExpired($this->rdv('reception', '-90 days')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statutsTerminaux(): array
    {
        return [
            'termine' => ['termine'],
            'annule' => ['annule'],
            'restitue' => ['restitue'],
            'restitue_partiel' => ['restitue_partiel'],
            'facture' => ['facture'],
            'paye' => ['paye'],
        ];
    }

    #[DataProvider('statutsTerminaux')]
    public function testTerminalStatusExpiresAfterGracePeriod(string $statut): void
    {
        $policy = new PublicTokenPolicy();
        // Clôturé et vieux de plus de 30 j → expiré.
        $this->assertTrue($policy->isTokenExpired($this->rdv($statut, '-40 days')));
    }

    #[DataProvider('statutsTerminaux')]
    public function testTerminalStatusStillValidWithinGracePeriod(string $statut): void
    {
        $policy = new PublicTokenPolicy();
        // Clôturé mais récent (< 30 j) → encore accessible (litige / restitution).
        $this->assertFalse($policy->isTokenExpired($this->rdv($statut, '-5 days')));
    }
}
