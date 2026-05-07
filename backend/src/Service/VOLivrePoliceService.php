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
     *
     * @param string $modePaiement One of VOLivrePolice::MODES_PAIEMENT
     * @param string|null $numeroCheque Required when $modePaiement === 'cheque'
     * @param string|null $nomBanque Optional, recommended for cheque/virement
     */
    public function createEntryForPurchase(
        VOPurchase $purchase,
        string $modePaiement,
        ?string $numeroCheque = null,
        ?string $nomBanque = null,
    ): VOLivrePolice {
        $this->validateModePaiementEncaissement($modePaiement, $numeroCheque);
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
        $entry->setModePaiement($modePaiement);
        $entry->setNumeroCheque($numeroCheque);
        $entry->setNomBanque($nomBanque);
        $entry->setVoPurchase($purchase);

        $entry->setIntegrityHash($this->computeIntegrityHash($entry));

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
     *
     * @param string $modePaiement One of VOLivrePolice::MODES_PAIEMENT
     */
    public function createEntryForDepotVente(
        VODepotVente $depot,
        string $modePaiement,
        ?string $numeroCheque = null,
        ?string $nomBanque = null,
    ): VOLivrePolice {
        $this->validateModePaiement($modePaiement, $numeroCheque);
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
        $entry->setModePaiement($modePaiement);
        $entry->setNumeroCheque($numeroCheque);
        $entry->setNomBanque($nomBanque);
        $entry->setVoDepotVente($depot);

        $entry->setIntegrityHash($this->computeIntegrityHash($entry));

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
        string $modePaiementVente,
        ?\DateTimeInterface $dateVente = null,
        ?string $numeroChequeVente = null,
        ?string $nomBanqueVente = null,
    ): void {
        // [C13] Guard : une entrée LP ne peut être vendue qu'une seule fois
        if ($acquisitionEntry->getDateVente() !== null) {
            throw new \LogicException(
                sprintf('LP entry #%d is already sold (dateVente: %s). Double-call prevented.', $acquisitionEntry->getId(), $acquisitionEntry->getDateVente()->format('d/m/Y'))
            );
        }

        $this->validateModePaiementEncaissement($modePaiementVente, $numeroChequeVente);

        $acquisitionEntry->setDateVente($dateVente ?? new \DateTime());
        $acquisitionEntry->setPrixVente($prixVente);
        $acquisitionEntry->setModePaiementVente($modePaiementVente);
        $acquisitionEntry->setNumeroChequeVente($numeroChequeVente);
        $acquisitionEntry->setNomBanqueVente($nomBanqueVente);
        $acquisitionEntry->setAcheteurNom($buyer->getNom());
        $acquisitionEntry->setAcheteurPrenom($buyer->getPrenom() ?? '');
        $acquisitionEntry->setAcheteurAdresse($buyer->getAdresse() ?? '');

        $acquisitionEntry->setIntegrityHash($this->computeIntegrityHash($acquisitionEntry));

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

    /**
     * Compute a deterministic SHA-256 integrity hash from immutable LP fields.
     */
    public function computeIntegrityHash(VOLivrePolice $entry): string
    {
        $data = [
            'atelierId' => $entry->getAtelierId(),
            'numeroOrdre' => $entry->getNumeroOrdre(),
            'type' => $entry->getType(),
            'dateAcquisition' => $entry->getDateAcquisition()->format('Y-m-d'),
            'descriptionBien' => $entry->getDescriptionBien(),
            'immatriculation' => $entry->getImmatriculation(),
            'vendeurNom' => $entry->getVendeurNom(),
            'vendeurPrenom' => $entry->getVendeurPrenom(),
            'vendeurAdresse' => $entry->getVendeurAdresse(),
            'vendeurIdType' => $entry->getVendeurIdType(),
            'vendeurIdNumber' => $entry->getVendeurIdNumber(),
            'vendeurIdDate' => $entry->getVendeurIdDate()->format('Y-m-d'),
            'prixAchat' => $entry->getPrixAchat(),
            'modePaiement' => $entry->getModePaiement(),
            'numeroCheque' => $entry->getNumeroCheque() ?? '',
            'nomBanque' => $entry->getNomBanque() ?? '',
            'dateVente' => $entry->getDateVente()?->format('Y-m-d') ?? '',
            'prixVente' => $entry->getPrixVente() ?? '',
            'modePaiementVente' => $entry->getModePaiementVente() ?? '',
            'numeroChequeVente' => $entry->getNumeroChequeVente() ?? '',
            'nomBanqueVente' => $entry->getNomBanqueVente() ?? '',
            'acheteurNom' => $entry->getAcheteurNom() ?? '',
            'acheteurPrenom' => $entry->getAcheteurPrenom() ?? '',
            'acheteurAdresse' => $entry->getAcheteurAdresse() ?? '',
        ];

        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    private function validateModePaiement(string $modePaiement, ?string $numeroCheque): void
    {
        if (!in_array($modePaiement, VOLivrePolice::MODES_PAIEMENT, true)) {
            throw new \InvalidArgumentException(
                sprintf('Mode de paiement invalide : "%s". Valeurs acceptées : %s.', $modePaiement, implode(', ', VOLivrePolice::MODES_PAIEMENT))
            );
        }

        if ($modePaiement === 'cheque' && empty($numeroCheque)) {
            throw new \InvalidArgumentException(
                'Livre de Police : le numéro de chèque est obligatoire pour un paiement par chèque.'
            );
        }
    }

    private function validateModePaiementEncaissement(string $modePaiement, ?string $numeroCheque): void
    {
        if (!in_array($modePaiement, VOLivrePolice::MODES_PAIEMENT_ENCAISSEMENT, true)) {
            throw new \InvalidArgumentException(
                sprintf('Mode de paiement invalide pour un encaissement : "%s". Valeurs acceptées : %s.', $modePaiement, implode(', ', VOLivrePolice::MODES_PAIEMENT_ENCAISSEMENT))
            );
        }

        $this->validateModePaiement($modePaiement, $numeroCheque);
    }
}

