<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Entity\VOFacture;
use App\Entity\VOPurchase;
use App\EventSubscriber\VOPurchaseWorkflowSubscriber;
use App\Service\AuditService;
use App\Service\NotificationDispatcher;
use App\Service\NotificationMessage;
use App\Service\VODocumentService;
use App\Service\VOLivrePoliceService;
use App\Service\VOMarginService;
use App\Service\VONumberingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

#[AllowMockObjectsWithoutExpectations]
class VOPurchaseWorkflowSubscriberTest extends TestCase
{
    private function createSubscriber(
        ?VOFacture $existingFacture = null,
        array $verdict = ['status' => 'vendable', 'summary' => 'Vendable maintenant'],
    ): array {
        $auditService = $this->createMock(AuditService::class);
        $documentService = $this->createMock(VODocumentService::class);
        $documentService->method('buildPurchaseSaleVerdict')->willReturn($verdict);
        $livrePoliceService = $this->createMock(VOLivrePoliceService::class);
        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existingFacture);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(VOFacture::class)->willReturn($repo);
        $em->method('persist');
        $em->method('flush');

        $numberingService = $this->createMock(VONumberingService::class);
        $numberingService->method('nextFactureNumber')->willReturn('VOF-2026-0001');

        $marginService = $this->createMock(VOMarginService::class);
        $marginService->method('calculateMarginVat')->willReturn([
            'margin' => '1000.00',
            'vat' => '166.67',
            'sale_price_ht' => '833.33',
            'sale_price_ttc' => '1000.00',
        ]);
        $marginService->method('calculateNormalVat')->willReturn([
            'vat' => '200.00',
            'sale_price_ht' => '1000.00',
            'sale_price_ttc' => '1200.00',
        ]);

        $workflow = $this->createMock(WorkflowInterface::class);

        $subscriber = new VOPurchaseWorkflowSubscriber(
            $auditService,
            $documentService,
            $livrePoliceService,
            $notificationDispatcher,
            $em,
            $numberingService,
            $marginService,
            $workflow,
        );

        return [
            $subscriber,
            $auditService,
            $documentService,
            $notificationDispatcher,
            $em,
            $numberingService,
            $marginService,
        ];
    }

    private function createPurchase(bool $sivRegistered = false, ?User $expert = null): VOPurchase
    {
        $vehicule = new Vehicule();
        $vehicule->setPlaque('AB-123-CD');
        $vehicule->setMarque('Yamaha');
        $vehicule->setModele('MT-07');

        $purchase = new VOPurchase();
        $purchase->setAtelierId(1);
        $purchase->setVehicule($vehicule);
        $purchase->setPurchasePrice('5000.00');
        $purchase->setTargetSalePrice('6000.00');
        $purchase->setRegimeTva('marge');
        $purchase->setExpert($expert);

        if ($sivRegistered) {
            $purchase->setSivStatus(VOPurchase::SIV_STATUS_ENREGISTREE);
            $purchase->setSivRecordedAt(new \DateTime());
        }

        return $purchase;
    }

    private function createExpert(): User
    {
        $user = new User();
        $user->setUsername('expert1');
        $user->setEmail('expert@atelier.fr');
        $user->setHashedPassword('hash');
        $user->setAtelierId(1);

        return $user;
    }

    private function createGuardEvent(VOPurchase $purchase, string $transitionName, string $from = 'place_a', string $to = 'place_b', array $marking = []): GuardEvent
    {
        $workflow = $this->createMock(WorkflowInterface::class);
        $markingObj = new Marking($marking);
        $transition = new Transition($transitionName, $from, $to);

        return new GuardEvent($purchase, $markingObj, $transition, $workflow);
    }

    private function createCompletedEvent(VOPurchase $purchase, string $transitionName, array $context = [], string $from = 'place_a', string $to = 'place_b', array $marking = []): CompletedEvent
    {
        $workflow = $this->createMock(WorkflowInterface::class);
        $markingObj = new Marking($marking);
        $transition = new Transition($transitionName, $from, $to);

        return new CompletedEvent($purchase, $markingObj, $transition, $workflow, $context);
    }

    private function createBuyer(): Client
    {
        $client = new Client();
        $client->setNom('Doe');
        $client->setPrenom('John');
        $client->setTelephone('0600000000');
        $client->setAtelierId(1);

        return $client;
    }

    // ─── Guard : mettre_en_vente ───

    public function testGuardMettreEnVenteAllowsVendable(): void
    {
        [$subscriber] = $this->createSubscriber();
        $purchase = $this->createPurchase();

        $event = $this->createGuardEvent($purchase, 'mettre_en_vente');

        $subscriber->onGuardMettreEnVente($event);
        $this->addToAssertionCount(1);
    }

    public function testGuardMettreEnVenteThrowsWhenNotVendable(): void
    {
        [$subscriber] = $this->createSubscriber(
            verdict: ['status' => 'non_vendable', 'summary' => '2 blocage(s) à lever avant vente.']
        );
        $purchase = $this->createPurchase();

        $event = $this->createGuardEvent($purchase, 'mettre_en_vente');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le véhicule ne peut pas être mis en vente');

        $subscriber->onGuardMettreEnVente($event);
    }

    public function testGuardMettreEnVenteIgnoresNonMettreEnVenteTransition(): void
    {
        [$subscriber] = $this->createSubscriber(
            verdict: ['status' => 'non_vendable', 'summary' => '2 blocage(s) à lever avant vente.']
        );
        $purchase = $this->createPurchase();

        $event = $this->createGuardEvent($purchase, 'confirmer');

        $subscriber->onGuardMettreEnVente($event);
        $this->addToAssertionCount(1);
    }

    // ─── Guard : vendre ───

    public function testGuardVendreAllowsWhenSivRegistered(): void
    {
        [$subscriber] = $this->createSubscriber();
        $purchase = $this->createPurchase(sivRegistered: true);

        $event = $this->createGuardEvent($purchase, 'vendre');

        $subscriber->onGuardVendre($event);
        $this->addToAssertionCount(1);
    }

    public function testGuardVendreThrowsWhenSivNotRegistered(): void
    {
        [$subscriber] = $this->createSubscriber();
        $purchase = $this->createPurchase(sivRegistered: false);

        $event = $this->createGuardEvent($purchase, 'vendre');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La DA SIV doit être enregistrée avant la vente.');

        $subscriber->onGuardVendre($event);
    }

    // ─── Completed : confirmer ───

    public function testCompletedConfirmerLogsAndNotifies(): void
    {
        [$subscriber, $auditService, , $notificationDispatcher] = $this->createSubscriber();
        $expert = $this->createExpert();
        $purchase = $this->createPurchase(expert: $expert);

        $auditService->expects($this->once())
            ->method('log')
            ->with('vo_purchase_confirmer', 'VOPurchase', $purchase->getId(), $this->stringContains('confirmer'));

        $notificationDispatcher->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(NotificationMessage::class));

        $event = $this->createCompletedEvent($purchase, 'confirmer');

        $subscriber->onCompletedConfirmer($event);
    }

    public function testCompletedConfirmerDoesNotNotifyWhenNoExpert(): void
    {
        [$subscriber, $auditService, , $notificationDispatcher] = $this->createSubscriber();
        $purchase = $this->createPurchase();

        $auditService->expects($this->once())->method('log');
        $notificationDispatcher->expects($this->never())->method('send');

        $event = $this->createCompletedEvent($purchase, 'confirmer');

        $subscriber->onCompletedConfirmer($event);
    }

    // ─── Completed : mettre_en_vente ───

    public function testCompletedMettreEnVenteLogs(): void
    {
        [$subscriber, $auditService] = $this->createSubscriber();
        $purchase = $this->createPurchase();

        $auditService->expects($this->once())
            ->method('log')
            ->with('vo_purchase_mettre_en_vente', 'VOPurchase', $purchase->getId(), $this->anything());

        $event = $this->createCompletedEvent($purchase, 'mettre_en_vente');

        $subscriber->onCompletedMettreEnVente($event);
    }

    // ─── Completed : vendre ───

    public function testCompletedVendreLogsAndGeneratesFacture(): void
    {
        [$subscriber, $auditService, , , $em, $numberingService] = $this->createSubscriber();
        $purchase = $this->createPurchase(sivRegistered: true);
        $buyer = $this->createBuyer();

        $auditService->expects($this->once())
            ->method('log')
            ->with('vo_purchase_vendre', 'VOPurchase', $purchase->getId(), $this->anything());

        $numberingService->expects($this->once())
            ->method('nextFactureNumber')
            ->with($purchase->getAtelierId());

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $event = $this->createCompletedEvent($purchase, 'vendre', ['buyer' => $buyer, 'salePrice' => '10000.00']);

        $subscriber->onCompletedVendre($event);
    }

    public function testCompletedVendreSkipsFactureWhenAlreadyExists(): void
    {
        $existingFacture = new VOFacture();
        $existingFacture->setNumeroFacture('VOF-2026-0001');
        $existingFacture->setClient($this->createBuyer());

        [$subscriber, $auditService, , , $em, $numberingService] = $this->createSubscriber(existingFacture: $existingFacture);
        $purchase = $this->createPurchase(sivRegistered: true);
        $buyer = $this->createBuyer();

        $auditService->expects($this->once())->method('log');
        $numberingService->expects($this->never())->method('nextFactureNumber');
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $event = $this->createCompletedEvent($purchase, 'vendre', ['buyer' => $buyer, 'salePrice' => '10000.00']);

        $subscriber->onCompletedVendre($event);
    }

    public function testCompletedVendreLogsOnlyWithoutContext(): void
    {
        [$subscriber, $auditService, , , $em] = $this->createSubscriber();
        $purchase = $this->createPurchase(sivRegistered: true);

        $auditService->expects($this->once())->method('log');
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $event = $this->createCompletedEvent($purchase, 'vendre', []);

        $subscriber->onCompletedVendre($event);
    }

    // ─── Completed : reserver ───

    public function testCompletedReserverLogsAndNotifies(): void
    {
        [$subscriber, $auditService, , $notificationDispatcher] = $this->createSubscriber();
        $expert = $this->createExpert();
        $purchase = $this->createPurchase(expert: $expert);

        $auditService->expects($this->once())
            ->method('log')
            ->with('vo_purchase_reserver', 'VOPurchase', $purchase->getId(), $this->anything());

        $notificationDispatcher->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(NotificationMessage::class));

        $event = $this->createCompletedEvent($purchase, 'reserver');

        $subscriber->onCompletedReserver($event);
    }

    // ─── Completed : retirer_de_la_vente ───

    public function testCompletedRetirerDeLaVenteLogs(): void
    {
        [$subscriber, $auditService] = $this->createSubscriber();
        $purchase = $this->createPurchase();

        $auditService->expects($this->once())
            ->method('log')
            ->with('vo_purchase_retirer_de_la_vente', 'VOPurchase', $purchase->getId(), $this->anything());

        $event = $this->createCompletedEvent($purchase, 'retirer_de_la_vente');

        $subscriber->onCompletedRetirerDeLaVente($event);
    }
}
