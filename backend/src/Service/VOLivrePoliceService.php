<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\VODepotVente;
use App\Entity\VOLivrePolice;
use App\Entity\VOPurchase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Auto-génération des entrées du Livre de Police.
 * Art. 321-7 Code Pénal + Décret 2009-1104.
 */
class VOLivrePoliceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VONumberingService $numberingService,
        private AuditService $auditService,
    ) {}

    /**
     * Create a Livre de Police entry for a purchase (rachat).
     */
    public function createEntryForPurchase(VOPurchase $purchase): VOLivrePolice
    {
        $seller = $purchase->getSeller();
        $vehicule = $purchase->getVehicule();

        $this->validateSellerIdentity($purchase);

        $entry = new VOLivrePolice();
        $entry->setAtelierId($purchase->getAtelierId());
        $entry->setNumeroOrdre($this->getNextNumeroOrdre($purchase->getAtelierId()));
        $entry->setType('achat');
        $entry->setDateAcquisition($purchase->getPurchaseDate() ?? new \DateTime());

        $entry->setDescriptionBien($this->buildDescription($vehicule));
        $entry->setImmatriculation($vehicule->getPlaque());

        $entry->setVendeurNom($seller->getNom());
        $entry->setVendeurPrenom($seller->getPrenom() ?? '');
        $entry->setVendeurAdresse($seller->getAdresse() ?? '');
        $entry->setVendeurIdType($purchase->getSellerIdType());
        $entry->setVendeurIdNumber($purchase->getSellerIdNumber());
        $entry->setVendeurIdDate($purchase->getSellerIdDate());

        $entry->setPrixAchat($purchase->getPurchasePrice());
        $entry->setVoPurchase($purchase);

        $this->em->persist($entry);

        $this->auditService->log(
            'lp_create_achat',
            'VOLivrePolice',
            null,
            sprintf('LP #%d — achat %s (purchase #%d)', $entry->getNumeroOrdre(), $vehicule->getPlaque(), $purchase->getId()),
        );

        return $entry;
    }

    /**
     * Create a Livre de Police entry for a dépôt-vente.
     */
    public function createEntryForDepotVente(VODepotVente $depot): VOLivrePolice
    {
        $deposant = $depot->getDeposant();
        $vehicule = $depot->getVehicule();

        $this->validateDeposantIdentity($depot);

        $entry = new VOLivrePolice();
        $entry->setAtelierId($depot->getAtelierId());
        $entry->setNumeroOrdre($this->getNextNumeroOrdre($depot->getAtelierId()));
        $entry->setType('depot_vente');
        $entry->setDateAcquisition($depot->getDateDebut());

        $entry->setDescriptionBien($this->buildDescription($vehicule) . ' [DÉPÔT-VENTE]');
        $entry->setImmatriculation($vehicule->getPlaque());

        $entry->setVendeurNom($deposant->getNom());
        $entry->setVendeurPrenom($deposant->getPrenom() ?? '');
        $entry->setVendeurAdresse($deposant->getAdresse() ?? '');
        $entry->setVendeurIdType($depot->getDeposantIdType());
        $entry->setVendeurIdNumber($depot->getDeposantIdNumber());
        $entry->setVendeurIdDate($depot->getDeposantIdDate());

        $entry->setPrixAchat($depot->getPrixVenteSouhaite());
        $entry->setVoDepotVente($depot);

        $this->em->persist($entry);

        $this->auditService->log(
            'lp_create_depot_vente',
            'VOLivrePolice',
            null,
            sprintf('LP #%d — dépôt-vente %s (depot #%d)', $entry->getNumeroOrdre(), $vehicule->getPlaque(), $depot->getId()),
        );

        return $entry;
    }

    /**
     * Record sale information on existing Livre de Police entry.
     *
     * This COMPLETES the existing acquisition entry by filling initially-null sale fields
     * (dateVente, prixVente, acheteurNom/Prenom/Adresse). This is NOT a mutation of
     * immutable data — the acquisition fields remain unchanged.
     *
     * Conformité LP (Art. 321-7 CP) : une seule ligne par objet, avec entrée ET sortie.
     * La numérotation et les champs d'acquisition sont immuables ; seuls les champs de
     * vente (initialement null) sont renseignés lors de la cession.
     */
    public function recordSale(
        VOLivrePolice $acquisitionEntry,
        Client $buyer,
        string $prixVente,
        ?\DateTimeInterface $dateVente = null,
    ): void {
        $acquisitionEntry->setDateVente($dateVente ?? new \DateTime());
        $acquisitionEntry->setPrixVente($prixVente);
        $acquisitionEntry->setAcheteurNom($buyer->getNom());
        $acquisitionEntry->setAcheteurPrenom($buyer->getPrenom() ?? '');
        $acquisitionEntry->setAcheteurAdresse($buyer->getAdresse() ?? '');

        $this->auditService->log(
            'lp_record_sale',
            'VOLivrePolice',
            $acquisitionEntry->getId(),
            sprintf('LP #%d — vente à %s %s pour %s €', $acquisitionEntry->getNumeroOrdre(), $buyer->getPrenom() ?? '', $buyer->getNom(), $prixVente),
        );
    }

    /**
     * Get next sequential number for an atelier.
     */
    private function getNextNumeroOrdre(?int $atelierId): int
    {
        return $this->numberingService->nextLivrePoliceOrder($atelierId);
    }

    private function buildDescription($vehicule): string
    {
        $parts = array_filter([
            $vehicule->getMarque(),
            $vehicule->getModele(),
            $vehicule->getCylindree() ? $vehicule->getCylindree() . 'cc' : null,
            $vehicule->getCouleur(),
            $vehicule->getVin() ? 'VIN: ' . $vehicule->getVin() : null,
            $vehicule->getAnnee() ? 'Année: ' . $vehicule->getAnnee() : null,
        ]);

        return implode(' — ', $parts);
    }

    private function validateSellerIdentity(VOPurchase $purchase): void
    {
        if (!$purchase->getSellerIdType() || !$purchase->getSellerIdNumber() || !$purchase->getSellerIdDate()) {
            throw new \InvalidArgumentException(
                'Livre de Police : identité du vendeur incomplète (type, numéro et date de pièce d\'identité obligatoires).'
            );
        }
    }

    private function validateDeposantIdentity(VODepotVente $depot): void
    {
        if (!$depot->getDeposantIdType() || !$depot->getDeposantIdNumber() || !$depot->getDeposantIdDate()) {
            throw new \InvalidArgumentException(
                'Livre de Police : identité du déposant incomplète (type, numéro et date de pièce d\'identité obligatoires).'
            );
        }
    }
}
