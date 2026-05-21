<?php

namespace App\Service;

use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Twig\Environment;

class HistoriqueEntretienService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Environment $twig,
    ) {}

    public function buildHistorique(Vehicule $vehicule): array
    {
        $rdvs = $vehicule->getRendezVous()->toArray();

        // Sort chronologically
        usort($rdvs, fn($a, $b) => $a->getDateRdv() <=> $b->getDateRdv());

        $interventions = [];
        foreach ($rdvs as $rdv) {
            // Only include completed or restituted RDVs
            if (!in_array($rdv->getStatut(), ['termine', 'restitue', 'restitue_partiel', 'facture', 'en_gardiennage'], true)) {
                continue;
            }

            $ordres = $rdv->getOrdresReparation();
            $mecanicienNom = null;
            foreach ($ordres as $or) {
                // OR doesn't have mecanicien; we get it from the RDV
            }

            // Pieces utilisées
            $pieces = [];
            foreach ($rdv->getPiecesUtilisees() as $pu) {
                $piece = $pu->getPiece();
                $pieces[] = [
                    'designation' => $piece->getNom(),
                    'reference' => $piece->getReference(),
                    'quantite' => $pu->getQuantite(),
                ];
            }

            // OR alertes
            $alertes = [];
            foreach ($rdv->getOrdresReparation() as $or) {
                if ($or->getAlertes()) {
                    $alertes = array_merge($alertes, $or->getAlertes());
                }
            }

            if ($rdv->getMecanicien()) {
                $mecanicienNom = $rdv->getMecanicien()->getNom();
            }

            $interventions[] = [
                'date' => $rdv->getDateRdv()->format('Y-m-d'),
                'kilometrage' => $rdv->getKilometrage(),
                'typeIntervention' => $rdv->getTypeIntervention(),
                'statut' => $rdv->getStatut(),
                'mecanicien' => $mecanicienNom,
                'ordres' => array_map(fn($or) => [
                    'id' => $or->getId(),
                    'numero' => $or->getNumeroOr(),
                ], $ordres->toArray()),
                'travaux' => array_map(fn($or) => $or->getTravaux(), $ordres->toArray()),
                'pieces' => $pieces,
                'alertes' => $alertes,
            ];
        }

        return [
            'vehicule' => [
                'id' => $vehicule->getId(),
                'plaque' => $vehicule->getPlaque(),
                'marque' => $vehicule->getMarque(),
                'modele' => $vehicule->getModele(),
                'annee' => $vehicule->getAnnee(),
                'cylindree' => $vehicule->getCylindree(),
                'typeMoto' => $vehicule->getTypeMoto(),
                'client' => $vehicule->getClient() ? [
                    'nom' => $vehicule->getClient()->getNom(),
                    'prenom' => $vehicule->getClient()->getPrenom(),
                ] : null,
            ],
            'interventions' => $interventions,
            'totalInterventions' => count($interventions),
            'generatedAt' => (new \DateTime())->format('c'),
        ];
    }

    public function generatePdf(Vehicule $vehicule): string
    {
        $historique = $this->buildHistorique($vehicule);

        $html = $this->twig->render('pdf/historique_entretien.html.twig', [
            'vehicule' => $vehicule,
            'historique' => $historique,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
