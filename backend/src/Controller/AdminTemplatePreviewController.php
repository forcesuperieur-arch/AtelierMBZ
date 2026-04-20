<?php

namespace App\Controller;

use App\Entity\Atelier;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/api/admin/templates')]
#[IsGranted('ROLE_ADMIN')]
class AdminTemplatePreviewController extends AbstractController
{
    private const TEMPLATES = [
        'ordre_reparation' => [
            'label' => 'Ordre de réparation',
            'template' => 'pdf/ordre_reparation.html.twig',
            'category' => 'atelier',
            'description' => 'Document de réception signé avant intervention atelier.',
        ],
        'facture' => [
            'label' => 'Facture atelier',
            'template' => 'pdf/facture.html.twig',
            'category' => 'atelier',
            'description' => 'Facture client avec lignes, TVA et totaux atelier.',
        ],
        'devis' => [
            'label' => 'Devis',
            'template' => 'pdf/devis.html.twig',
            'category' => 'atelier',
            'description' => 'Devis avant travaux avec validité et acompte proposé.',
        ],
        'rapport_intervention' => [
            'label' => 'Rapport d\'intervention',
            'template' => 'pdf/rapport_intervention.html.twig',
            'category' => 'atelier',
            'description' => 'Compte-rendu méca avec essai routier et signatures.',
        ],
        'historique_entretien' => [
            'label' => 'Historique entretien',
            'template' => 'pdf/historique_entretien.html.twig',
            'category' => 'atelier',
            'description' => 'Synthèse des interventions passées sur le véhicule.',
        ],
        'vo_pv_rachat' => [
            'label' => 'PV de rachat',
            'template' => 'pdf/vo_pv_rachat.html.twig',
            'category' => 'vo',
            'description' => 'Procès-verbal de rachat VO avec identité vendeur.',
        ],
        'vo_facture' => [
            'label' => 'Facture VO',
            'template' => 'pdf/vo_facture.html.twig',
            'category' => 'vo',
            'description' => 'Facture de vente VO avec mentions légales et TVA.',
        ],
        'vo_contrat_depot_vente' => [
            'label' => 'Contrat dépôt-vente',
            'template' => 'pdf/vo_contrat_depot_vente.html.twig',
            'category' => 'vo',
            'description' => 'Mandat de dépôt-vente avec prix cible et commission.',
        ],
        'vo_livre_police' => [
            'label' => 'Livre de police',
            'template' => 'pdf/vo_livre_police.html.twig',
            'category' => 'vo',
            'description' => 'Extrait du registre légal achat/vente VO.',
        ],
        'vo_da_siv' => [
            'label' => 'Préparation DA SIV',
            'template' => 'pdf/vo_da_siv.html.twig',
            'category' => 'vo',
            'description' => 'Préparation administrative de la déclaration d\'achat.',
        ],
        'vo_mandat_immatriculation' => [
            'label' => 'Mandat d\'immatriculation',
            'template' => 'pdf/vo_mandat_immatriculation.html.twig',
            'category' => 'vo',
            'description' => 'Support prérempli pour formalités SIV côté acheteur.',
        ],
        'vo_remise_en_etat' => [
            'label' => 'Remise en état VO',
            'template' => 'pdf/vo_remise_en_etat.html.twig',
            'category' => 'vo',
            'description' => 'Synthèse FRE avec coûts estimés, pièces et arbitrage.',
        ],
    ];

    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $result = [];
        foreach (self::TEMPLATES as $code => $meta) {
            $result[] = [
                'code' => $code,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'template' => $meta['template'],
                'description' => $meta['description'],
            ];
        }

        return $this->json($result);
    }

    #[Route('/{code}/preview', methods: ['GET'])]
    public function preview(string $code): Response
    {
        if (!isset(self::TEMPLATES[$code])) {
            return $this->json(['error' => 'Template inconnu'], 404);
        }

        $meta = self::TEMPLATES[$code];
        $atelier = $this->resolveAtelier();
        $branding = $this->buildBrandingContext($atelier);
        $sampleData = $this->buildSampleData($code);

        $html = $this->twig->render($meta['template'], [
            ...$branding,
            ...$sampleData,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="preview-%s.pdf"', $code),
        ]);
    }

    private function resolveAtelier(): ?Atelier
    {
        $user = $this->getUser();
        $atelierId = method_exists($user, 'getAtelierId') ? $user->getAtelierId() : null;

        if ($atelierId) {
            return $this->em->getRepository(Atelier::class)->find($atelierId);
        }

        return null;
    }

    private function buildBrandingContext(?Atelier $atelier): array
    {
        $logoDataUri = null;
        $logoUrl = $atelier?->getLogoUrl();
        if ($logoUrl) {
            $relativePath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
            $filePath = $this->projectDir . '/public' . $relativePath;
            if (is_file($filePath) && is_readable($filePath)) {
                $contents = file_get_contents($filePath);
                if ($contents !== false) {
                    $mimeType = mime_content_type($filePath) ?: 'image/png';
                    $logoDataUri = sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
                }
            }
        }

        return [
            'atelier' => $atelier,
            'logo_data_uri' => $logoDataUri,
        ];
    }

    private function buildSamplePhoto(string $label): array
    {
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="100%%" height="100%%" fill="#e5e7eb"/><text x="50%%" y="50%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="18" fill="#374151">%s</text></svg>',
            htmlspecialchars($label, ENT_QUOTES)
        );

        return [
            'src' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'label' => $label,
            'takenAt' => (new \DateTime())->format('d/m/Y H:i'),
        ];
    }

    private function buildSampleData(string $code): array
    {
        $client = [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'telephone' => '06 12 34 56 78',
            'email' => 'jean.dupont@example.fr',
            'adresse' => '12 rue de la Paix',
            'cp' => '75001',
            'ville' => 'Paris',
        ];

        $vehicule = [
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

        return match ($code) {
            'ordre_reparation' => [
                'or' => [
                    'numeroOr' => 'OR-PREVIEW-001',
                    'createdAt' => new \DateTime(),
                    'snapClientNom' => 'Dupont',
                    'snapClientPrenom' => 'Jean',
                    'snapVehiculeMarque' => 'Yamaha',
                    'snapVehiculeModele' => 'MT-07',
                    'snapVehiculePlaque' => 'AB-123-CD',
                    'kilometrage' => 15420,
                    'travaux' => "Révision complète 20 000 km\nVidange huile moteur + filtre\nContrôle freins AV/AR",
                    'etatVehicule' => 'Bon état général, rayure carénage gauche pré-existante',
                    'signatureClient' => null,
                    'lignes' => [
                        ['designation' => 'Vidange huile moteur + filtre', 'quantite' => 1, 'prixUnitaireHt' => '45.00', 'totalHt' => '45.00'],
                        ['designation' => 'Plaquettes frein AV (jeu)', 'quantite' => 1, 'prixUnitaireHt' => '32.00', 'totalHt' => '32.00'],
                        ['designation' => 'Main d\'œuvre révision', 'quantite' => 2, 'prixUnitaireHt' => '65.00', 'totalHt' => '130.00'],
                    ],
                    'totalHt' => '207.00',
                    'totalTva' => '41.40',
                    'totalTtc' => '248.40',
                ],
                'rdv' => [
                    'client' => $client,
                    'vehicule' => $vehicule,
                    'typeIntervention' => 'Révision périodique',
                ],
                'reception_photos' => [
                    $this->buildSamplePhoto('Vue avant'),
                    $this->buildSamplePhoto('Côté gauche'),
                    $this->buildSamplePhoto('Côté droit'),
                ],
            ],

            'facture' => [
                'facture' => [
                    'numeroFacture' => 'FAC-PREVIEW-001',
                    'dateCreation' => new \DateTime(),
                    'dateEcheance' => new \DateTime('+30 days'),
                    'client' => $client,
                    'vehicule' => $vehicule,
                    'lignes' => [
                        ['designation' => 'Vidange huile moteur + filtre', 'reference' => 'ENT-VID-001', 'quantite' => 1, 'prixUnitaireHt' => '45.00', 'tauxTva' => '20', 'totalLigneHt' => '45.00'],
                        ['designation' => 'Plaquettes frein AV (jeu)', 'reference' => 'FR-PLAQ-AV', 'quantite' => 1, 'prixUnitaireHt' => '32.00', 'tauxTva' => '20', 'totalLigneHt' => '32.00'],
                        ['designation' => 'Main d\'œuvre révision', 'reference' => 'MO-REV-20000', 'quantite' => 2, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneHt' => '130.00'],
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
            ],

            'devis' => [
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
                        ['designation' => 'Remplacement chaîne + pignons', 'descriptionDetail' => 'Dépose/repose et réglage tension', 'typeLigne' => 'Main d\'œuvre', 'quantite' => 1.5, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneTtc' => '117.00'],
                        ['designation' => 'Essai et contrôle final', 'descriptionDetail' => 'Vérification routière après remontage', 'typeLigne' => 'Main d\'œuvre', 'quantite' => 0.5, 'prixUnitaireHt' => '65.00', 'tauxTva' => '20', 'totalLigneTtc' => '39.00'],
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
            ],

            'rapport_intervention' => [
                'rdv' => [
                    'id' => 42,
                    'dateDebut' => new \DateTime(),
                    'dateFin' => new \DateTime('+2 hours'),
                    'typeIntervention' => 'Révision périodique',
                    'commentaire' => 'Client signale un bruit au freinage avant.',
                ],
                'rapport' => [
                    'statut' => 'signe',
                    'dureeMinutes' => 120,
                    'travauxRealises' => "Remplacement des plaquettes avant, contrôle freins arrière et vérification générale de sécurité.",
                    'alertes' => ['Pneu arrière à surveiller d\'ici 2 000 km.'],
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
                        ['label' => 'Témoin ABS', 'ok' => true, 'commentaire' => 'Aucune alerte tableau de bord.'],
                    ],
                    'anomalies' => null,
                    'actionsCorrectives' => 'Contrôle final et serrage au couple réalisés.',
                ],
                'client' => $client,
                'vehicule' => $vehicule,
                'mecanicien' => ['nom' => 'Martin', 'prenom' => 'Lucas'],
                'report_photos' => [
                    $this->buildSamplePhoto('Frein avant remplacé'),
                    $this->buildSamplePhoto('Contrôle restitution'),
                    $this->buildSamplePhoto('Vue finale véhicule'),
                ],
            ],

            'historique_entretien' => [
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
                            'travaux' => ['Vidange moteur', 'Remplacement filtre à huile', 'Contrôle sécurité'],
                            'pieces' => [['designation' => 'Filtre à huile', 'reference' => 'HF204', 'quantite' => 1]],
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
            ],

            'vo_pv_rachat' => [
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
                    'notes' => 'Moto en bon état, quelques traces d\'usure normales.',
                ],
                'companion_signature' => null,
            ],

            'vo_facture' => [
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
            ],

            'vo_contrat_depot_vente' => [
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
                    'conditionsRestitution' => 'Restitution possible avec préavis de 48h et rendez-vous préalable.',
                    'assuranceInfo' => 'Le véhicule reste assuré par le déposant durant la période du mandat.',
                    'notes' => 'Dépôt-vente 90 jours, commission 10%.',
                    'status' => 'actif',
                ],
                'companion_signature' => null,
            ],

            'vo_livre_police' => [
                'entries' => [
                    [
                        'numeroOrdre' => 'LP-2026-0001',
                        'type' => 'achat',
                        'dateAcquisition' => new \DateTime('-30 days'),
                        'descriptionBien' => 'Yamaha MT-07 689cc 2022 — AB-123-CD',
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
                        'descriptionBien' => 'Honda CB650R 649cc 2021 — EF-456-GH',
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
            ],

            'vo_da_siv' => [
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
            ],

            'vo_mandat_immatriculation' => [
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
            ],

            'vo_remise_en_etat' => [
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
                    'label' => 'Campagne FRE #1',
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
                    'diagnostic' => 'Pneu arrière proche témoin, micro-rayures carénage, entretien global satisfaisant.',
                    'workshop' => 'Remplacement pneu AR et contrôle géométrie effectués. Nettoyage terminé.',
                    'business' => 'Arbitrage validé pour garder une marge saine tout en sécurisant la vente rapide.',
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
                    ['libelle' => 'Polish carrosserie 500ml', 'reference' => 'POL-500', 'quantity' => 1, 'status' => 'utilisée', 'estimatedTotalCostHt' => '12.00', 'actualTotalCostHt' => '12.00'],
                ],
            ],

            default => [],
        };
    }
}
