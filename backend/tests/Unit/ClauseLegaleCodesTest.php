<?php

namespace App\Tests\Unit;

use App\Entity\ClauseLegale;
use PHPUnit\Framework\TestCase;

class ClauseLegaleCodesTest extends TestCase
{
    public function testLegalClauseCodesIncludeMandatoryBackOfficeEntries(): void
    {
        $this->assertContains('rgpd', ClauseLegale::CODES);
        $this->assertContains('retention', ClauseLegale::CODES);
        $this->assertContains('mandat_reparation', ClauseLegale::CODES);
        $this->assertContains('mentions_legales', ClauseLegale::CODES);
    }
}
