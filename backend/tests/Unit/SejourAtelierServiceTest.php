<?php

namespace App\Tests\Unit;

use App\Service\JoursOuvresService;
use App\Service\ReglesAtelier;
use App\Service\SejourAtelierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Calcul « heures ouvrées » de l'alerte « moto en atelier depuis plus de 72h ouvré ».
 * Le calendrier est injecté, le calcul est donc testé sans base de données.
 */
class SejourAtelierServiceTest extends TestCase
{
    private function service(): SejourAtelierService
    {
        // Stubs : le calcul testé ici est pur (calendrier injecté) — ni la base, ni
        // le calendrier des jours ouvrés, ni les réglages ne sont sollicités.
        return new SejourAtelierService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(JoursOuvresService::class),
            $this->createStub(ReglesAtelier::class),
        );
    }

    /** Calendrier de test : ouvert du lundi au vendredi, fermé samedi/dimanche. */
    private function fermeLeWeekEnd(): callable
    {
        return static fn (\DateTimeImmutable $jour) => in_array((int) $jour->format('N'), [6, 7], true);
    }

    private function toujoursOuvert(): callable
    {
        return static fn (\DateTimeImmutable $jour) => false;
    }

    public function testCompteLesHeuresReellesQuandTousLesJoursSontOuverts(): void
    {
        // Arrange : lundi 08:00 → mercredi 08:00 = 48 h calendaires
        $debut = new \DateTimeImmutable('2026-07-06 08:00:00');
        $fin = new \DateTimeImmutable('2026-07-08 08:00:00');

        // Act
        $heures = $this->service()->heuresHorsFermeture($debut, $fin, $this->toujoursOuvert());

        // Assert
        $this->assertEqualsWithDelta(48.0, $heures, 0.01);
    }

    public function testLeWeekEndNeComptePas(): void
    {
        // Arrange : vendredi 08:00 → lundi 08:00 = 72 h calendaires, dont 48 h de week-end
        $debut = new \DateTimeImmutable('2026-07-10 08:00:00');
        $fin = new \DateTimeImmutable('2026-07-13 08:00:00');

        // Act
        $heures = $this->service()->heuresHorsFermeture($debut, $fin, $this->fermeLeWeekEnd());

        // Assert : seules les 16 h du vendredi + les 8 h du lundi sont comptées
        $this->assertEqualsWithDelta(24.0, $heures, 0.01);
    }

    public function testMotoRecueVendrediNAlertePasLeLundiMatin(): void
    {
        // Arrange : reçue vendredi 09:00, on est lundi 09:00 (72 h calendaires)
        $debut = new \DateTimeImmutable('2026-07-10 09:00:00');
        $fin = new \DateTimeImmutable('2026-07-13 09:00:00');

        // Act
        $heures = $this->service()->heuresHorsFermeture($debut, $fin, $this->fermeLeWeekEnd());

        // Assert : 24 h ouvrées seulement → très en dessous du seuil de 72 h
        $this->assertLessThan(SejourAtelierService::SEUIL_HEURES_DEFAUT, $heures);
    }

    public function testDepassementDuSeuilApresTroisJoursOuvresPleins(): void
    {
        // Arrange : reçue lundi 08:00, on est vendredi 09:00 → 4 jours ouvrés + 1 h
        $debut = new \DateTimeImmutable('2026-07-06 08:00:00');
        $fin = new \DateTimeImmutable('2026-07-10 09:00:00');

        // Act
        $heures = $this->service()->heuresHorsFermeture($debut, $fin, $this->fermeLeWeekEnd());

        // Assert
        $this->assertEqualsWithDelta(97.0, $heures, 0.01);
        $this->assertGreaterThan(SejourAtelierService::SEUIL_HEURES_DEFAUT, $heures);
    }

    public function testJourFermeIsoleEstIgnore(): void
    {
        // Arrange : mercredi 14 juillet férié entre mardi 08:00 et jeudi 08:00
        $debut = new \DateTimeImmutable('2026-07-13 08:00:00');
        $fin = new \DateTimeImmutable('2026-07-16 08:00:00');
        $ferie = static fn (\DateTimeImmutable $jour) => $jour->format('Y-m-d') === '2026-07-14';

        // Act
        $heures = $this->service()->heuresHorsFermeture($debut, $fin, $ferie);

        // Assert : 72 h calendaires − 24 h de férié
        $this->assertEqualsWithDelta(48.0, $heures, 0.01);
    }

    public function testFinAnterieureOuEgaleAuDebutDonneZero(): void
    {
        $debut = new \DateTimeImmutable('2026-07-13 08:00:00');

        $this->assertSame(0.0, $this->service()->heuresHorsFermeture($debut, $debut, $this->toujoursOuvert()));
        $this->assertSame(
            0.0,
            $this->service()->heuresHorsFermeture($debut, $debut->modify('-1 day'), $this->toujoursOuvert()),
        );
    }

    public function testLaReceptionTraceeFaitFoiPourLaDateDArrivee(): void
    {
        // Arrange
        $reception = new \DateTimeImmutable('2026-07-20 09:00:00');
        $premierEvenement = new \DateTimeImmutable('2026-07-20 09:00:00');
        $dateRdv = new \DateTimeImmutable('2026-07-18 08:00:00');

        // Act
        $arrivee = $this->service()->choisirDateArrivee($reception, $premierEvenement, $dateRdv);

        // Assert : on ne remonte pas à la date du RDV, la moto est arrivée le 20
        $this->assertSame($reception, $arrivee);
    }

    public function testSansHistoriqueOnRetombeSurLaDateDuRdv(): void
    {
        $dateRdv = new \DateTimeImmutable('2026-07-18 08:00:00');

        $arrivee = $this->service()->choisirDateArrivee(null, null, $dateRdv);

        $this->assertSame($dateRdv, $arrivee);
    }

    public function testUnChangementDeStatutTardifNeRemetPasLeCompteurAZero(): void
    {
        // Arrange : dossier sans réception tracée, dont le seul événement d'atelier
        // est une mise en pause du jour — la moto est là depuis la date du RDV.
        $dateRdv = new \DateTimeImmutable('2026-05-20 11:45:00');
        $miseEnPauseAujourdhui = new \DateTimeImmutable('2026-07-27 12:00:00');

        // Act
        $arrivee = $this->service()->choisirDateArrivee(null, $miseEnPauseAujourdhui, $dateRdv);

        // Assert
        $this->assertSame($dateRdv, $arrivee, 'La date du RDV borne l\'ancienneté vers le passé.');
    }

    public function testUnEvenementAnterieurAuRdvGagne(): void
    {
        // Moto déposée en avance : l'événement d'atelier précède la date du RDV.
        $dateRdv = new \DateTimeImmutable('2026-07-20 08:00:00');
        $depotAnticipe = new \DateTimeImmutable('2026-07-17 16:30:00');

        $arrivee = $this->service()->choisirDateArrivee(null, $depotAnticipe, $dateRdv);

        $this->assertSame($depotAnticipe, $arrivee);
    }

    public function testStatutsEnAtelierExcluentLesDossiersClotures(): void
    {
        $statuts = SejourAtelierService::STATUTS_EN_ATELIER;

        $this->assertContains('reception', $statuts);
        $this->assertContains('en_cours', $statuts);
        $this->assertContains('en_gardiennage', $statuts);
        foreach (['en_attente', 'reserve', 'confirme', 'termine', 'restitue', 'annule', 'no_show'] as $exclu) {
            $this->assertNotContains($exclu, $statuts);
        }
    }
}
