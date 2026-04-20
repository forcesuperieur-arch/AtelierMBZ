<?php

namespace App\Tests\Unit;

use App\Entity\VODocument;
use App\Entity\VOPurchase;
use App\Service\VOCompanionWorkflowService;
use PHPUnit\Framework\TestCase;

final class VOCompanionWorkflowServiceTest extends TestCase
{
    public function testJustificatifDomicileIsNotAvailableInCompanionFlow(): void
    {
        $service = new VOCompanionWorkflowService();
        $purchase = new VOPurchase();

        $required = $service->getRequiredDocuments($purchase);
        $additional = $service->getAdditionalDocumentOptions($purchase);

        self::assertNotContains(VODocument::TYPE_JUSTIFICATIF_DOMICILE, $required);
        self::assertNotContains(VODocument::TYPE_JUSTIFICATIF_DOMICILE, $additional);
    }

    public function testJustificatifDomicileUsesZeroRetention(): void
    {
        self::assertSame(0, VODocument::RETENTION_YEARS[VODocument::TYPE_JUSTIFICATIF_DOMICILE]);
    }
}
