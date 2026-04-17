<?php

namespace App\Tests\Unit;

use App\Service\AdminConfigValidator;
use App\Service\AbsenceConflictChecker;
use PHPUnit\Framework\TestCase;

class AdminValidationServicesTest extends TestCase
{
    public function testConfigValidatorRejectsInvalidPercentagesAndHours(): void
    {
        $validator = new AdminConfigValidator();

        $errors = $validator->validateConfigPayload(
            [
                'tva_mo_taux' => 120,
                'accompte_pourcentage' => -5,
            ],
            [
                [
                    'jour_semaine' => 1,
                    'is_ouvert' => true,
                    'heure_ouverture' => '18:00',
                    'heure_fermeture' => '09:00',
                    'pause_debut' => '12:30',
                    'pause_fin' => '12:00',
                ],
            ]
        );

        $this->assertNotEmpty($errors);
        $this->assertContains('Le taux de TVA main d’œuvre doit être compris entre 0 et 100.', $errors);
        $this->assertContains('Le pourcentage d’acompte doit être compris entre 0 et 100.', $errors);
        $this->assertContains('Le jour 1 a des horaires incohérents : ouverture doit être avant fermeture.', $errors);
        $this->assertContains('Le jour 1 a une pause incohérente : début doit être avant fin.', $errors);
    }

    public function testAbsenceConflictCheckerDetectsOverlap(): void
    {
        $checker = new AbsenceConflictChecker();

        $hasOverlap = $checker->hasConflict(
            new \DateTimeImmutable('2026-04-10'),
            new \DateTimeImmutable('2026-04-12'),
            [
                [
                    'start' => new \DateTimeImmutable('2026-04-11'),
                    'end' => new \DateTimeImmutable('2026-04-13'),
                ],
            ]
        );

        $this->assertTrue($hasOverlap);
    }
}
