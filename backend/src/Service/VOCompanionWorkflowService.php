<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\VODepotVente;
use App\Entity\VODocument;
use App\Entity\VOPurchase;
use App\Entity\Vehicule;

class VOCompanionWorkflowService
{
    public function ensureToken(VOPurchase|VODepotVente $record): bool
    {
        return $record->ensureCompanionToken();
    }

    public function getMode(VOPurchase|VODepotVente $record): string
    {
        return $record instanceof VOPurchase ? 'purchase' : 'depot';
    }

    public function getPartyRoleLabel(VOPurchase|VODepotVente $record): string
    {
        return $record instanceof VOPurchase ? 'vendeur' : 'deposant';
    }

    public function getParty(VOPurchase|VODepotVente $record): ?Client
    {
        return $record instanceof VOPurchase ? $record->getSeller() : $record->getDeposant();
    }

    public function getRequiredDocuments(VOPurchase|VODepotVente $record): array
    {
        return $record instanceof VOPurchase
            ? [
                VODocument::TYPE_CARTE_GRISE,
                VODocument::TYPE_NON_GAGE,
                VODocument::TYPE_CERFA_CESSION_ACHAT,
            ]
            : [
                VODocument::TYPE_CARTE_GRISE,
            ];
    }

    public function getAdditionalDocumentOptions(VOPurchase|VODepotVente $record): array
    {
        return [
            VODocument::TYPE_CONTROLE_TECHNIQUE,
            VODocument::TYPE_AUTRE,
        ];
    }

    public function getGeneratedDocuments(VOPurchase|VODepotVente $record): array
    {
        if ($record instanceof VOPurchase) {
            return [
                [
                    'type' => VODocument::TYPE_CERFA_CESSION_ACHAT,
                    'label' => 'Certificat de cession CERFA 15776',
                    'url' => sprintf('/api/vo/purchases/%d/cerfa-cession-achat/pdf', $record->getId()),
                    'description' => 'Vrai CERFA 15776 prérempli pour l\'achat au professionnel, à faire signer puis archiver dans le dossier VO.',
                    'nature' => 'cerfa',
                ],
                [
                    'type' => VODocument::TYPE_PV_RACHAT,
                    'label' => 'PV de rachat généré',
                    'url' => sprintf('/api/vo/purchases/%d/pv-rachat/pdf', $record->getId()),
                    'description' => 'PDF opposable généré à partir des informations vendeur, véhicule et signature PDA.',
                    'nature' => 'legal',
                ],
                [
                    'type' => VODocument::TYPE_DA_SIV,
                    'label' => 'Déclaration d\'achat CERFA 13751',
                    'url' => sprintf('/api/vo/purchases/%d/da-siv/pdf', $record->getId()),
                    'description' => 'Rendu réglementaire aligné sur le CERFA 13751*02, à archiver avec le récépissé SIV une fois la DA enregistrée.',
                    'nature' => 'cerfa',
                ],
                [
                    'type' => VODocument::TYPE_MANDAT_IMMATRICULATION,
                    'label' => 'Mandat immatriculation CERFA 13757',
                    'url' => sprintf('/api/vo/purchases/%d/mandat-immat/pdf', $record->getId()),
                    'description' => 'Rendu réglementaire aligné sur le CERFA 13757*03, à faire signer puis archiver dans le dossier de vente.',
                    'nature' => 'cerfa',
                ],
            ];
        }

        return [
            [
                'type' => VODocument::TYPE_CONTRAT_DEPOT_VENTE,
                'label' => 'Contrat dépôt-vente généré',
                'url' => sprintf('/api/vo/depots/%d/contrat/pdf', $record->getId()),
                'description' => 'PDF opposable généré à partir des informations déposant, véhicule et signature PDA.',
                'nature' => 'legal',
            ],
            [
                'type' => VODocument::TYPE_MANDAT_IMMATRICULATION,
                'label' => 'Mandat immatriculation CERFA 13757',
                'url' => sprintf('/api/vo/depots/%d/mandat-immat/pdf', $record->getId()),
                'description' => 'Rendu réglementaire aligné sur le CERFA 13757*03, à faire signer puis archiver dans le dossier de vente.',
                'nature' => 'cerfa',
            ],
        ];
    }

    public function buildSteps(VOPurchase|VODepotVente $record, array $documents): array
    {
        $party = $this->getParty($record);
        $documentTypes = array_values(array_unique(array_map(
            static fn (VODocument $document): string => $document->getType(),
            $documents,
        )));
        $requiredDocuments = $this->getRequiredDocuments($record);

        $identityType = $record instanceof VOPurchase ? $record->getSellerIdType() : $record->getDeposantIdType();
        $identityNumber = $record instanceof VOPurchase ? $record->getSellerIdNumber() : $record->getDeposantIdNumber();
        $identityDate = $record instanceof VOPurchase ? $record->getSellerIdDate() : $record->getDeposantIdDate();

        $sellerCompleted = trim((string) $identityType) !== ''
            && trim((string) $identityNumber) !== ''
            && $identityDate instanceof \DateTimeInterface;

        $vehicleCompleted = in_array(VODocument::TYPE_CARTE_GRISE, $documentTypes, true)
            && $this->hasVehicleCoreData($record->getVehicule());

        $extraRequiredDocuments = array_values(array_filter(
            $requiredDocuments,
            static fn (string $type): bool => !in_array($type, [VODocument::TYPE_PIECE_IDENTITE, VODocument::TYPE_CARTE_GRISE], true),
        ));
        $missingExtraDocuments = array_values(array_diff($extraRequiredDocuments, $documentTypes));
        $documentsCompleted = $missingExtraDocuments === [];
        $signatureCompleted = $record->hasCompanionSignature();

        return [
            'seller' => [
                'label' => ucfirst($this->getPartyRoleLabel($record)),
                'completed' => $sellerCompleted,
                'requiresImmediateDestruction' => true,
                'identity' => [
                    'type' => $identityType,
                    'number' => $identityNumber,
                    'date' => $identityDate?->format('Y-m-d'),
                    'nom' => trim((($party?->getPrenom()) ?: '') . ' ' . (($party?->getNom()) ?: '')),
                ],
            ],
            'vehicle' => [
                'label' => 'Vehicule',
                'completed' => $vehicleCompleted,
            ],
            'documents' => [
                'label' => 'Documents',
                'completed' => $documentsCompleted,
                'required' => $extraRequiredDocuments,
                'missing' => $missingExtraDocuments,
            ],
            'signature' => [
                'label' => 'Signature client',
                'completed' => $signatureCompleted,
                'signedAt' => $record->getCompanionSignedAt()?->format(DATE_ATOM),
            ],
            'requiredDocuments' => $requiredDocuments,
            'additionalDocumentOptions' => $this->getAdditionalDocumentOptions($record),
            'uploadedTypes' => $documentTypes,
            'allComplete' => $sellerCompleted && $vehicleCompleted && $documentsCompleted && $signatureCompleted,
            'completedCount' => count(array_filter([$sellerCompleted, $vehicleCompleted, $documentsCompleted, $signatureCompleted])),
            'totalCount' => 4,
        ];
    }

    private function hasVehicleCoreData(?Vehicule $vehicule): bool
    {
        if (!$vehicule instanceof Vehicule) {
            return false;
        }

        return trim((string) $vehicule->getMarque()) !== ''
            && trim((string) $vehicule->getModele()) !== ''
            && (trim((string) $vehicule->getPlaque()) !== '' || trim((string) $vehicule->getVin()) !== '');
    }
}