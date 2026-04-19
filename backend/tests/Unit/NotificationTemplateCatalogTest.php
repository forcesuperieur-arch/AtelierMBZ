<?php

namespace App\Tests\Unit;

use App\Service\NotificationTemplateCatalog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class NotificationTemplateCatalogTest extends TestCase
{
    public function testDefaultTemplatesCoverSmsAndEmailScenarios(): void
    {
        $catalog = new NotificationTemplateCatalog($this->createMock(EntityManagerInterface::class));

        $defaults = $catalog->getDefaults();
        $index = [];

        foreach ($defaults as $definition) {
            $index[$definition['code'] . ':' . $definition['channel']] = true;
        }

        self::assertNotEmpty($defaults);
        self::assertArrayHasKey('rdv_confirmation:email', $index);
        self::assertArrayHasKey('rdv_confirmation:sms', $index);
        self::assertArrayHasKey('travaux_termines:email', $index);
        self::assertArrayHasKey('travaux_termines:sms', $index);
    }
}
