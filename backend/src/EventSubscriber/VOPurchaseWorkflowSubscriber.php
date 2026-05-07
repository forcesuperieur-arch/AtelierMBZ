<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\VOFacture;
use App\Entity\VOPurchase;
use App\Service\AuditService;
use App\Service\NotificationDispatcher;
use App\Service\NotificationMessage;
use App\Service\VODocumentService;
use App\Service\VOLivrePoliceService;
use App\Service\VOMarginService;
use App\Service\VONumberingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Side-effects subscriber for the vo_purchase workflow.
 *
 * Guards block transitions via exceptions.
 * Completed listeners handle audit logging, notifications and invoice generation.
 */
final class VOPurchaseWorkflowSubscriber
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly VODocumentService $documentService,
        private readonly VOLivrePoliceService $livrePoliceService,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly EntityManagerInterface $em,
        private readonly VONumberingService $numberingService,
        private readonly VOMarginService $marginService,
        #[Target('vo_purchase')]
        private readonly WorkflowInterface $workflow,
    ) {}

    #[AsEventListener(event: 'workflow.vo_purchase.guard.mettre_en_vente')]
    public function onGuardMettreEnVente(GuardEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();
        $verdict = $this->documentService->buildPurchaseSaleVerdict($purchase);

        if ($verdict['status'] !== 'vendable') {
            throw new \RuntimeException(sprintf(
                'Le véhicule ne peut pas être mis en vente : %s',
                $verdict['summary']
            ));
        }
    }

    #[AsEventListener(event: 'workflow.vo_purchase.guard.vendre')]
    public function onGuardVendre(GuardEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        if (!$purchase->isSivRegistered()) {
            throw new \RuntimeException('La DA SIV doit être enregistrée avant la vente.');
        }
    }

    #[AsEventListener(event: 'workflow.vo_purchase.completed.confirmer')]
    public function onCompletedConfirmer(CompletedEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        $this->auditService->log(
            'vo_purchase_confirmer',
            'VOPurchase',
            $purchase->getId(),
            'Transition confirmer effectuée'
        );

        $this->notifyExpert(
            $purchase,
            'VO confirmé',
            sprintf('Le véhicule %s a été confirmé et mis en stock.', $this->purchaseLabel($purchase))
        );
    }

    #[AsEventListener(event: 'workflow.vo_purchase.completed.mettre_en_vente')]
    public function onCompletedMettreEnVente(CompletedEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        $this->auditService->log(
            'vo_purchase_mettre_en_vente',
            'VOPurchase',
            $purchase->getId(),
            'Transition mettre_en_vente effectuée'
        );
    }

    #[AsEventListener(event: 'workflow.vo_purchase.completed.vendre')]
    public function onCompletedVendre(CompletedEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        $this->auditService->log(
            'vo_purchase_vendre',
            'VOPurchase',
            $purchase->getId(),
            'Transition vendre effectuée'
        );

        $context = $event->getContext();
        if (!empty($context['buyer']) && $context['buyer'] instanceof Client && isset($context['salePrice'])) {
            $this->generateFactureIfNeeded($purchase, $context['buyer'], (string) $context['salePrice']);
        }
    }

    #[AsEventListener(event: 'workflow.vo_purchase.completed.reserver')]
    public function onCompletedReserver(CompletedEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        $this->auditService->log(
            'vo_purchase_reserver',
            'VOPurchase',
            $purchase->getId(),
            'Transition reserver effectuée'
        );

        $this->notifyExpert(
            $purchase,
            'VO réservé',
            sprintf('Le véhicule %s a été réservé.', $this->purchaseLabel($purchase))
        );
    }

    #[AsEventListener(event: 'workflow.vo_purchase.completed.retirer_de_la_vente')]
    public function onCompletedRetirerDeLaVente(CompletedEvent $event): void
    {
        /** @var VOPurchase $purchase */
        $purchase = $event->getSubject();

        $this->auditService->log(
            'vo_purchase_retirer_de_la_vente',
            'VOPurchase',
            $purchase->getId(),
            'Transition retirer_de_la_vente effectuée'
        );
    }

    private function notifyExpert(VOPurchase $purchase, string $subject, string $body): void
    {
        $expert = $purchase->getExpert();
        if (!$expert instanceof User) {
            return;
        }

        $email = $expert->getEmail();
        if (empty($email)) {
            return;
        }

        $msg = new NotificationMessage(
            'email',
            $purchase->getAtelierId() ?? 0,
            $email,
            $body,
            $subject,
            'vo_purchase_notification',
            'VOPurchase',
            $purchase->getId(),
        );

        $this->notificationDispatcher->send($msg);
    }

    private function generateFactureIfNeeded(VOPurchase $purchase, Client $buyer, string $salePrice): void
    {
        $existing = $this->em->getRepository(VOFacture::class)->findOneBy(['voPurchase' => $purchase]);
        if ($existing instanceof VOFacture) {
            return;
        }

        $numero = $this->numberingService->nextFactureNumber($purchase->getAtelierId());
        $vatCalc = $purchase->getRegimeTva() === 'marge'
            ? $this->marginService->calculateMarginVat($purchase->getPurchasePrice(), $salePrice)
            : $this->marginService->calculateNormalVat($salePrice);

        $facture = new VOFacture();
        $facture->setAtelierId($purchase->getAtelierId() ?? 0);
        $facture->setNumeroFacture($numero);
        $facture->setVoPurchase($purchase);
        $facture->setClient($buyer);
        $facture->setVehicule($purchase->getVehicule());
        $facture->setRegimeTva($purchase->getRegimeTva());
        $facture->setPrixAchatHt($purchase->getPurchasePrice());
        $facture->setMentionTvaMarge($purchase->getRegimeTva() === 'marge');
        $facture->setTotalHt($vatCalc['sale_price_ht']);
        $facture->setTotalTva($vatCalc['vat']);
        $facture->setTotalTtc($vatCalc['sale_price_ttc']);

        $vehicule = $purchase->getVehicule();
        if ($vehicule !== null) {
            $facture->setImmatriculation($vehicule->getPlaque());
            $facture->setVinFacture($vehicule->getVin());
            $facture->setKilometrage($vehicule->getMileage());
            $facture->setDatePremiereMiseEnCirculationFacture($vehicule->getDatePremiereMiseEnCirculation());
        }

        $this->em->persist($facture);
        $this->em->flush();
    }

    private function purchaseLabel(VOPurchase $purchase): string
    {
        $vehicule = $purchase->getVehicule();
        if ($vehicule === null) {
            return sprintf('Purchase #%d', $purchase->getId());
        }

        return sprintf('%s %s (%s)', $vehicule->getMarque() ?? '', $vehicule->getModele() ?? '', $vehicule->getPlaque() ?? '');
    }
}
