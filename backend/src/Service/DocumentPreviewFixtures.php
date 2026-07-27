<?php

namespace App\Service;

/**
 * Jeux de données fictives pour prévisualiser les documents PDF.
 *
 * Extrait du contrôleur d'administration pour être réutilisable par le test de
 * fumée qui rend chaque document enregistré : c'est ce test qui garantit qu'un
 * template listé dans l'administration existe réellement et ne casse pas.
 *
 * Les jeux de données visent l'état « document complet » : c'est ce qu'un
 * administrateur veut voir pour juger une mise en page, et cela exerce les
 * branches conditionnelles des templates.
 */
class DocumentPreviewFixtures
{
    /**
     * @return array<string, mixed> Variables Twig, hors contexte de marque
     *                              (atelier / logo), fourni par l'appelant.
     */
    public function forCode(string $code): array
    {
        $client = $this->client();
        $vehicule = $this->vehicule($client);

        return match ($code) {
            'ordre_reparation' => $this->ordreReparation($client, $vehicule),
            'facture' => $this->facture($client, $vehicule),
            'devis' => $this->devis($client, $vehicule),
            'rapport_intervention' => $this->rapportIntervention($client, $vehicule),
            'etat_des_lieux' => $this->etatDesLieux($client, $vehicule),
            'historique_entretien' => $this->historiqueEntretien($vehicule),
            'vo_pv_rachat' => $this->voPvRachat($client, $vehicule),
            'vo_facture' => $this->voFacture($client, $vehicule),
            'vo_contrat_depot_vente' => $this->voContratDepotVente($client, $vehicule),
            'vo_livre_police' => $this->voLivrePolice(),
            'vo_da_siv' => $this->voDaSiv($client, $vehicule),
            'vo_mandat_immatriculation' => $this->voMandatImmatriculation($client, $vehicule),
            'vo_remise_en_etat' => $this->voRemiseEnEtat($client, $vehicule),
            'dashboard_report' => $this->dashboardReport(),
            default => [],
        };
    }

    /**
     * Vignette neutre en SVG : évite d'embarquer un binaire dans le dépôt et
     * reste lisible une fois rendue par dompdf.
     */
    public function samplePhoto(string $label): array
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200">'
            . '<rect width="100%%" height="100%%" fill="#e5e7eb"/>'
            . '<text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" '
            . 'font-family="Arial" font-size="18" fill="#374151">%s</text></svg>',
            htmlspecialchars($label, ENT_QUOTES)
        );

        return [
            'src' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'label' => $label,
            'takenAt' => (new \DateTime())->format('d/m/Y H:i'),
        ];
    }

    private function client(): array
    {
        return [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'telephone' => '06 12 34 56 78',
            'email' => 'jean.dupont@example.fr',
            'adresse' => '12 rue de la Paix',
            'cp' => '75001',
            'ville' => 'Paris',
        ];
    }

    private function vehicule(array $client): array
    {
        return [
            'marque' => 'Yamaha',
            'modele' => 'MT-07',
            'annee' => 2022,
            'plaque' => 'AB-123-CD',
            'immatriculation' => 'AB-123-CD',
            'vin' => 'JYARN231000012345',
            'cylindree' => '689',
            'couleur' => 'Bleu Yamaha',
            'typeMoto' => 'Roadster',
            'categorieNom' => 'Roadster',
            'mileage' => 15420,
            'datePremiereMiseEnCirculation' => new \DateTime('2022-03-15'),
            'client' => $client,
        ];
    }

    private function ordreReparation(array $client, array $vehicule): array
    {
        return [
            'or' => [
                'numeroOr' => 'OR-PREVIEW-001',
                'createdAt' => new \DateTime(),
                'statut' => 'en_cours',
                'typeOr' => 'initial',
                'snapClientNom' => 'Dupont',
                'snapClientPrenom' => 'Jean',
                'snapVehiculeMarque' => 'Yamaha',
                'snapVehiculeModele' => 'MT-07',
                'snapVehiculePlaque' => 'AB-123-CD',
                'kilometrage' => 15420,
                'travaux' => "Révision complète 20 000 km\nVidange huile moteur + filtre\nContrôle freins AV/AR",
                'etatVehicule' => 'Bon état général, rayure carénage gauche pré-existante.',
                'montantEstime' => '248.40',
                'signatureClient' => null,
                'signatureAtelierReception' => null,
                'mechanicNotes' => "Plaquettes avant sous le témoin d'usure, remplacement effectué.\nContrôle du serrage au couple réalisé.",
                'mechanicNotesUpdatedAt' => new \DateTime('-1 hour'),
                'mechanicCheckup' => [
                    'pneumatiques' => ['label' => 'Pneumatiques', 'value' => 'Conformes', 'note' => 'AR proche du témoin'],
                    'freinage' => ['label' => 'Freinage', 'value' => 'Plaquettes AV remplacées'],
                    'eclairage' => ['label' => 'Éclairage', 'value' => 'Conforme'],
                    'transmission' => ['label' => 'Transmission', 'value' => 'Chaîne retendue et graissée'],
                ],
                'travauxRealises' => "Vidange moteur et remplacement du filtre à huile.\nRemplacement des plaquettes de frein avant.\nContrôle général de sécurité et essai routier.",
                'alertes' => ['Pneu arrière à surveiller, remplacement conseillé sous 2 000 km.'],
                'recommandations' => 'Prévoir le remplacement du pneu arrière au prochain passage atelier.',
                'garantie' => 'Garantie atelier 30 jours sur la prestation et les pièces montées.',
                'kilometrageRestitution' => 15425,
                'signatureMecanicien' => null,
                'signeMecanicienAt' => new \DateTime('-30 minutes'),
                'signatureClientRestitution' => null,
                'signedHash' => 'or-preview-a1b2c3d4e5f6',
                'signedAt' => new \DateTime('-2 hours'),
            ],
            'rdv' => [
                'client' => $client,
                'vehicule' => $vehicule,
                'typeIntervention' => 'Révision périodique',
                'dateRdv' => new \DateTime(),
                'heureRdv' => new \DateTime('today 09:30'),
                'commentaire' => 'Le client signale un bruit au freinage avant.',
                'pont' => ['nom' => 'Pont 2'],
                'mecanicien' => ['nom' => 'Martin', 'prenom' => 'Lucas'],
                'prixEstime' => '248.40',
                'tempsEstime' => 150,
                'prestationsSnapshot' => [
                    ['designation' => 'Vidange huile moteur + filtre', 'prix_ht' => 45.0, 'prix_ttc' => 54.0, 'duree' => 45],
                    ['designation' => 'Plaquettes frein AV (jeu)', 'prix_ht' => 32.0, 'prix_ttc' => 38.4, 'duree' => 30],
                    ['designation' => "Main d'œuvre révision", 'prix_ht' => 130.0, 'prix_ttc' => 156.0, 'duree' => 75],
                ],
            ],
            'reception_photos' => [
                $this->samplePhoto('Vue avant'),
                $this->samplePhoto('Côté gauche'),
                $this->samplePhoto('Côté droit'),
            ],
            'intervention_photos' => [
                $this->samplePhoto('Plaquettes déposées'),
                $this->samplePhoto('Montage neuf'),
            ],
            'restitution_photos' => [
                $this->samplePhoto('Véhicule restitué'),
            ],
        ];
    }

    private function facture(array $client, array $vehicule): array
    {
        return [
            'facture' => [
                'numeroFacture' => 'FAC-PREVIEW-001',
                'dateCreation' => new \DateTime(),
                'dateEcheance' => new \DateTime('+30 days'),
                'client' => $client,
                'vehicule' => $vehicule,
                'lignes' => [
                    ['designation' => 'Vidange huile moteur + filtre', 'reference' => 'ENT-VID-001', 'quantite' => 1, 'prixUnitaireHt' => '45.00', 'tauxTva' => '20', 'totalLigneHt' => '45.00'],
                    ['designation' => 'Plaquettes frein AV (jeu)', 'reference' => 'FR-PLAQ-AV', 'quantite' => 1, 'prixUnitaireHt' => '32.00', 'tauxTva' => '20', 'totalLigneHt' => '32.00'],
                    ['designation' => "Main d'œuvre révision", 'reference' => 'MO-REV-20000', 'quantite' => 2, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneHt' => '130.00'],
                ],
                'totalMoHt' => '130.00',
                'totalPiecesHt' => '77.00',
                'totalHt' => '207.00',
                'remiseMontant' => '0.00',
                'tvaMoTaux' => '20',
                'tvaMo' => '26.00',
                'tvaPiecesTaux' => '20',
                'tvaPieces' => '15.40',
                'totalTva' => '41.40',
                'totalTtc' => '248.40',
                'statut' => 'emise',
                'notes' => 'Merci pour votre confiance. Paiement comptant à réception du véhicule.',
                'snapClientNom' => 'Dupont',
                'snapClientPrenom' => 'Jean',
                'snapClientTelephone' => '06 12 34 56 78',
                'snapClientEmail' => 'jean.dupont@example.fr',
                'snapClientAdresse' => '12 rue de la Paix, 75001 Paris',
                'snapVehiculeMarque' => 'Yamaha',
                'snapVehiculeModele' => 'MT-07',
                'snapVehiculePlaque' => 'AB-123-CD',
            ],
        ];
    }

    private function devis(array $client, array $vehicule): array
    {
        return [
            'devis' => [
                'numeroDevis' => 'DEV-PREVIEW-001',
                'dateCreation' => new \DateTime(),
                'dateValidite' => new \DateTime('+30 days'),
                'validiteJours' => 30,
                'client' => $client,
                'vehicule' => $vehicule,
                'kilometrage' => 15420,
                'lignes' => [
                    ['designation' => 'Kit chaîne DID 525', 'descriptionDetail' => 'Chaîne renforcée + pignon + couronne', 'typeLigne' => 'Pièce', 'quantite' => 1, 'prixUnitaireHt' => '85.00', 'tauxTva' => '20', 'totalLigneTtc' => '102.00'],
                    ['designation' => 'Remplacement chaîne + pignons', 'descriptionDetail' => 'Dépose/repose et réglage tension', 'typeLigne' => "Main d'œuvre", 'quantite' => 1.5, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneTtc' => '117.00'],
                    ['designation' => 'Essai et contrôle final', 'descriptionDetail' => 'Vérification routière après remontage', 'typeLigne' => "Main d'œuvre", 'quantite' => 0.5, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneTtc' => '39.00'],
                ],
                'totalMoHt' => '130.00',
                'totalPiecesHt' => '85.00',
                'totalHt' => '215.00',
                'totalTva' => '43.00',
                'remiseMontant' => '0.00',
                'remisePourcentage' => '0',
                'acompteDemande' => '90.00',
                'totalTtc' => '258.00',
                'statut' => 'en_attente',
                'notesClient' => 'Sous réserve de confirmation après démontage si usure complémentaire constatée.',
                'snapClientNom' => 'Dupont',
                'snapClientPrenom' => 'Jean',
                'snapClientTelephone' => '06 12 34 56 78',
                'snapClientEmail' => 'jean.dupont@example.fr',
                'snapVehiculeMarque' => 'Yamaha',
                'snapVehiculeModele' => 'MT-07',
                'snapVehiculePlaque' => 'AB-123-CD',
            ],
        ];
    }

    private function rapportIntervention(array $client, array $vehicule): array
    {
        return [
            'rdv' => [
                'id' => 42,
                'dateDebut' => new \DateTime(),
                'dateFin' => new \DateTime('+2 hours'),
                'typeIntervention' => 'Révision périodique',
                'commentaire' => 'Le client signale un bruit au freinage avant.',
            ],
            'rapport' => [
                'statut' => 'signe',
                'dureeMinutes' => 120,
                'travauxRealises' => 'Remplacement des plaquettes avant, contrôle des freins arrière et vérification générale de sécurité.',
                'alertes' => ["Pneu arrière à surveiller d'ici 2 000 km."],
                'recommandations' => 'Prévoir un remplacement du pneu arrière au prochain passage atelier.',
                'garantie' => 'Garantie atelier 30 jours sur la prestation et les pièces montées.',
                'prochaineRevisionKm' => 20000,
                'prochaineRevisionDate' => new \DateTime('+6 months'),
                'kilometrageRestitution' => 15425,
                'signatureMecanicien' => true,
                'signeMecanicienAt' => new \DateTime('-1 hour'),
                'signatureClient' => false,
                'signeClientAt' => null,
                'signedHash' => 'ri-preview-a1b2c3d4',
            ],
            'essai' => [
                'kmDebut' => 15420,
                'kmFin' => 15425,
                'distance' => 5,
                'dureeMinutes' => 12,
                'pointsControle' => [
                    ['label' => 'Freinage avant', 'ok' => true, 'commentaire' => 'Bruit disparu après remplacement.'],
                    ['label' => 'Tenue de route', 'ok' => true, 'commentaire' => 'RAS sur chaussée urbaine.'],
                    ['label' => 'Témoin ABS', 'ok' => true, 'commentaire' => 'Aucune alerte au tableau de bord.'],
                ],
                'anomalies' => null,
                'actionsCorrectives' => 'Contrôle final et serrage au couple réalisés.',
            ],
            'client' => $client,
            'vehicule' => $vehicule,
            'mecanicien' => ['nom' => 'Martin', 'prenom' => 'Lucas'],
            'report_photos' => [
                $this->samplePhoto('Frein avant remplacé'),
                $this->samplePhoto('Contrôle restitution'),
                $this->samplePhoto('Vue finale véhicule'),
            ],
        ];
    }

    private function etatDesLieux(array $client, array $vehicule): array
    {
        return [
            'edl' => [
                'id' => 17,
                'createdAt' => new \DateTime(),
                'kilometrage' => 15420,
                'niveauCarburant' => 'moitie',
                'observations' => "Rayure sur le carénage gauche et léger jeu au levier d'embrayage, constatés avant intervention.",
                'signatureClient' => null,
                'signedByNom' => 'Claire Durand',
                'signedAt' => new \DateTime('-10 minutes'),
                'signedHash' => 'edl-preview-9f8e7d6c5b4a',
                'snapClientNom' => 'Dupont',
                'snapClientPrenom' => 'Jean',
                'snapVehiculeMarque' => 'Yamaha',
                'snapVehiculeModele' => 'MT-07',
                'snapVehiculePlaque' => 'AB-123-CD',
            ],
            'rdv' => [
                'id' => 42,
                'client' => $client,
                'vehicule' => $vehicule,
                'dateRdv' => new \DateTime(),
                'heureRdv' => new \DateTime('today 09:30'),
            ],
            'niveau_carburant_label' => 'Moitié',
            'photos_entree' => [
                $this->samplePhoto('Vue avant'),
                $this->samplePhoto('Côté gauche'),
                $this->samplePhoto('Côté droit'),
                $this->samplePhoto('Compteur'),
            ],
        ];
    }

    private function historiqueEntretien(array $vehicule): array
    {
        return [
            'vehicule' => $vehicule,
            'historique' => [
                'totalInterventions' => 3,
                'generatedAt' => (new \DateTime())->format('d/m/Y H:i'),
                'interventions' => [
                    [
                        'date' => new \DateTime('-6 months'),
                        'kilometrage' => 10200,
                        'typeIntervention' => 'Révision',
                        'mecanicien' => 'Lucas Martin',
                        'ordres' => [['numero' => 'OR-2025-0198']],
                        'travaux' => ['Vidange moteur', "Remplacement filtre à huile", 'Contrôle sécurité'],
                        'pieces' => [['designation' => "Filtre à huile", 'reference' => 'HF204', 'quantite' => 1]],
                        'alertes' => [],
                    ],
                    [
                        'date' => new \DateTime('-3 months'),
                        'kilometrage' => 12800,
                        'typeIntervention' => 'Réparation',
                        'mecanicien' => 'Nicolas Bernard',
                        'ordres' => [['numero' => 'OR-2026-0042']],
                        'travaux' => ['Remplacement kit chaîne', 'Réglage tension'],
                        'pieces' => [['designation' => 'Kit chaîne DID 525', 'reference' => 'DID-525', 'quantite' => 1]],
                        'alertes' => ['Usure avancée des plaquettes avant constatée.'],
                    ],
                    [
                        'date' => new \DateTime(),
                        'kilometrage' => 15420,
                        'typeIntervention' => 'Révision',
                        'mecanicien' => 'Lucas Martin',
                        'ordres' => [['numero' => 'OR-2026-0101']],
                        'travaux' => ['Remplacement plaquettes avant', 'Contrôle freins arrière', 'Essai routier'],
                        'pieces' => [['designation' => 'Plaquettes avant', 'reference' => 'BREMBO-SA', 'quantite' => 1]],
                        'alertes' => [],
                    ],
                ],
            ],
        ];
    }

    private function voPvRachat(array $client, array $vehicule): array
    {
        return [
            'purchase' => [
                'id' => 1,
                'purchaseDate' => new \DateTime(),
                'purchasePrice' => '4500.00',
                'seller' => $client,
                'vehicule' => $vehicule,
                'sellerIdType' => 'CNI',
                'sellerIdNumber' => '1234567890',
                'sellerIdDate' => new \DateTime('-2 years'),
                'nonGageDate' => new \DateTime('-3 days'),
                'controleTechniqueOk' => true,
                'expert' => null,
                'notes' => "Moto en bon état, quelques traces d'usure normales.",
            ],
            'companion_signature' => null,
        ];
    }

    private function voFacture(array $client, array $vehicule): array
    {
        return [
            'facture' => [
                'numeroFacture' => 'VOF-PREVIEW-001',
                'dateCreation' => new \DateTime(),
                'dateEcheance' => null,
                'client' => $client,
                'vehicule' => $vehicule,
                'regimeTva' => 'marge',
                'mentionTvaMarge' => true,
                'mentionGarantieConformite' => true,
                'prixAchatHt' => '4500.00',
                'totalHt' => '5416.67',
                'totalTva' => '183.33',
                'totalTtc' => '5600.00',
                'immatriculation' => 'AB-123-CD',
                'vinFacture' => 'JYARN231000012345',
                'kilometrage' => 15420,
                'datePremiereMiseEnCirculationFacture' => new \DateTime('2022-03-15'),
                'notes' => 'Véhicule révisé et garanti 12 mois minimum.',
                'snapClientNom' => 'Dupont',
                'snapClientPrenom' => 'Jean',
                'snapClientTelephone' => '06 12 34 56 78',
                'snapClientEmail' => 'jean.dupont@example.fr',
                'snapClientAdresse' => '12 rue de la Paix, 75001 Paris',
                'snapVehiculeMarque' => 'Yamaha',
                'snapVehiculeModele' => 'MT-07',
                'snapVehiculePlaque' => 'AB-123-CD',
            ],
        ];
    }

    private function voContratDepotVente(array $client, array $vehicule): array
    {
        return [
            'depot' => [
                'id' => 1,
                'dateDebut' => new \DateTime(),
                'dateFin' => new \DateTime('+90 days'),
                'dureeMandat' => 90,
                'prixMinimum' => '5000.00',
                'prixVenteSouhaite' => '5490.00',
                'commission' => '500.00',
                'commissionTaux' => '10',
                'commissionType' => 'pourcentage',
                'commissionValeur' => '10.00',
                'deposant' => $client,
                'deposantIdType' => 'CNI',
                'deposantIdNumber' => '1234567890',
                'deposantIdDate' => new \DateTime('-2 years'),
                'vehicule' => $vehicule,
                'conditionsRestitution' => 'Restitution possible avec préavis de 48 h et rendez-vous préalable.',
                'assuranceInfo' => 'Le véhicule reste assuré par le déposant durant la période du mandat.',
                'notes' => 'Dépôt-vente 90 jours, commission 10 %.',
                'status' => 'actif',
            ],
            'companion_signature' => null,
        ];
    }

    private function voLivrePolice(): array
    {
        return [
            'entries' => [
                [
                    'numeroOrdre' => 'LP-2026-0001',
                    'type' => 'achat',
                    'dateAcquisition' => new \DateTime('-30 days'),
                    'descriptionBien' => 'Yamaha MT-07 689 cm³ 2022 — AB-123-CD',
                    'immatriculation' => 'AB-123-CD',
                    'vendeurPrenom' => 'Jean',
                    'vendeurNom' => 'Dupont',
                    'vendeurIdType' => 'CNI',
                    'vendeurIdNumber' => '1234567890',
                    'prixAchat' => '4500.00',
                    'prixVente' => null,
                    'acheteurPrenom' => null,
                    'acheteurNom' => null,
                    'dateVente' => null,
                ],
                [
                    'numeroOrdre' => 'LP-2026-0002',
                    'type' => 'achat',
                    'dateAcquisition' => new \DateTime('-15 days'),
                    'descriptionBien' => 'Honda CB650R 649 cm³ 2021 — EF-456-GH',
                    'immatriculation' => 'EF-456-GH',
                    'vendeurPrenom' => 'Marie',
                    'vendeurNom' => 'Martin',
                    'vendeurIdType' => 'Passeport',
                    'vendeurIdNumber' => '22AA00000',
                    'prixAchat' => '6200.00',
                    'prixVente' => '7800.00',
                    'acheteurPrenom' => 'Pierre',
                    'acheteurNom' => 'Bernard',
                    'dateVente' => new \DateTime('-5 days'),
                ],
            ],
        ];
    }

    private function voDaSiv(array $client, array $vehicule): array
    {
        return [
            'purchase' => [
                'id' => 1,
                'purchaseDate' => new \DateTime(),
                'purchasePrice' => '4500.00',
                'seller' => $client,
                'vehicule' => $vehicule,
                'sellerIdType' => 'CNI',
                'sellerIdNumber' => '1234567890',
                'sellerIdDate' => new \DateTime('-2 years'),
                'nonGageDate' => new \DateTime('-3 days'),
                'controleTechniqueOk' => true,
                'sivStatus' => 'a_preparer',
                'sivReference' => null,
            ],
            'blockers' => [],
        ];
    }

    private function voMandatImmatriculation(array $client, array $vehicule): array
    {
        return [
            'record' => [
                'id' => 1,
                'reference' => 'VENTE-VO-2026-004',
                'vehicule' => $vehicule,
            ],
            'vehicle' => $vehicule,
            'seller' => $client,
            'buyer' => [
                'nom' => 'Bernard',
                'prenom' => 'Pierre',
                'adresse' => '45 avenue des Champs',
                'cp' => '69001',
                'ville' => 'Lyon',
                'telephone' => '06 98 76 54 32',
                'email' => 'pierre.bernard@example.fr',
            ],
        ];
    }

    private function voRemiseEnEtat(array $client, array $vehicule): array
    {
        return [
            'document' => [
                'reference' => 'REVO-PREVIEW-001',
                'signed' => true,
                'signature' => [
                    'signedAt' => new \DateTime('-1 day'),
                    'signedBy' => ['prenom' => 'Claire', 'nom' => 'Durand'],
                    'ip' => '127.0.0.1',
                ],
                'signatureData' => null,
                'hash' => 'fre-preview-6f89d1ab',
                'generatedAt' => new \DateTime(),
            ],
            'snapshot' => [],
            'campaign' => [
                'label' => 'Campagne FRE n° 1',
                'index' => 1,
                'title' => 'Préparation sécurité et cosmétique avant mise en vente',
                'status' => 'validée',
                'priority' => 'haute',
                'requestedAt' => new \DateTime('-7 days'),
                'validatedAt' => new \DateTime('-2 days'),
            ],
            'record' => [
                'reference' => 'RACHAT-2026-001',
                'typeLabel' => 'Rachat VO',
                'counterparty' => $client,
                'purchasePrice' => '4500.00',
                'targetSalePrice' => '5600.00',
            ],
            'vehicle' => $vehicule,
            'notes' => [
                'diagnostic' => 'Pneu arrière proche du témoin, micro-rayures carénage, entretien global satisfaisant.',
                'workshop' => 'Remplacement pneu AR et contrôle géométrie effectués. Nettoyage terminé.',
                'business' => 'Arbitrage validé pour garder une marge saine tout en sécurisant une vente rapide.',
            ],
            'summary' => [
                'estimatedMoCost' => '130.00',
                'estimatedPartsCost' => '185.00',
                'estimatedTotalCost' => '315.00',
                'actualTotalCost' => '298.00',
                'pendingPiecesCount' => 1,
            ],
            'lines' => [
                ['libelle' => 'Montage + équilibrage pneu AR', 'prestationCode' => 'FRE-PNEU-AR', 'quantity' => 1, 'status' => 'terminé', 'estimatedMinutes' => 60, 'estimatedTotalHt' => '65.00', 'actualTotalHt' => '60.00'],
                ['libelle' => 'Nettoyage complet et lustrage', 'prestationCode' => 'FRE-COSM-01', 'quantity' => 1, 'status' => 'terminé', 'estimatedMinutes' => 45, 'estimatedTotalHt' => '65.00', 'actualTotalHt' => '65.00'],
            ],
            'pieces' => [
                ['libelle' => 'Pneu AR Michelin Road 5 180/55 ZR17', 'reference' => 'MIC-ROAD5-180', 'quantity' => 1, 'status' => 'reçue', 'estimatedTotalCostHt' => '120.00', 'actualTotalCostHt' => '118.00'],
                ['libelle' => 'Polish carrosserie 500 ml', 'reference' => 'POL-500', 'quantity' => 1, 'status' => 'utilisée', 'estimatedTotalCostHt' => '12.00', 'actualTotalCostHt' => '12.00'],
            ],
        ];
    }

    private function dashboardReport(): array
    {
        return [
            'period' => [
                'from' => (new \DateTime('-30 days'))->format('d/m/Y'),
                'to' => (new \DateTime())->format('d/m/Y'),
            ],
            'kpi' => [
                'nb_rdvs' => 128,
                'ca_ht' => '24680.50',
                'ca_mo_ht' => '15420.00',
                'ca_pieces_ht' => '9260.50',
            ],
            'trend' => [
                ['snapshot_date' => (new \DateTime('-4 days'))->format('d/m/Y'), 'ca_du_jour_ht' => '820.00'],
                ['snapshot_date' => (new \DateTime('-3 days'))->format('d/m/Y'), 'ca_du_jour_ht' => '1140.50'],
                ['snapshot_date' => (new \DateTime('-2 days'))->format('d/m/Y'), 'ca_du_jour_ht' => '965.00'],
                ['snapshot_date' => (new \DateTime('-1 day'))->format('d/m/Y'), 'ca_du_jour_ht' => '1310.00'],
            ],
            'topServices' => [
                ['type_intervention' => 'Révision périodique', 'count' => 42, 'revenue' => '8940.00'],
                ['type_intervention' => 'Pneumatiques', 'count' => 27, 'revenue' => '5410.00'],
                ['type_intervention' => 'Freinage', 'count' => 19, 'revenue' => '3120.50'],
            ],
            'mecas' => [
                ['mecanicien_nom' => 'Lucas Martin', 'nb_rdvs' => 58, 'ca_genere' => '11240.00'],
                ['mecanicien_nom' => 'Nicolas Bernard', 'nb_rdvs' => 44, 'ca_genere' => '8760.50'],
                ['mecanicien_nom' => 'Claire Durand', 'nb_rdvs' => 26, 'ca_genere' => '4680.00'],
            ],
        ];
    }
}
