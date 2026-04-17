<?php

namespace App\Service;

use App\Entity\AnnulationRdv;
use App\Entity\RendezVous;

class RendezVousWorkflowService
{
    public function recordCancellation(
        RendezVous $rdv,
        string $motif,
        string $source = 'atelier',
        ?string $commentaire = null,
        ?int $userId = null,
    ): AnnulationRdv {
        $motif = trim($motif);
        if ($motif === '' || !in_array($motif, AnnulationRdv::MOTIFS, true)) {
            throw new \InvalidArgumentException('Motif d\'annulation invalide ou manquant');
        }

        if (!in_array($source, AnnulationRdv::SOURCES, true)) {
            throw new \InvalidArgumentException('Source d\'annulation invalide');
        }

        return (new AnnulationRdv())
            ->setRendezVous($rdv)
            ->setMotif($motif)
            ->setSource($source)
            ->setCommentaire($commentaire ?: null)
            ->setAnnulePar($userId)
            ->setAtelierId($rdv->getAtelierId())
            ->setStatutAvantAnnulation($rdv->getStatut())
            ->setHeureRdvOriginal($rdv->getHeureRdv());
    }

    public function startWorkSession(RendezVous $rdv, ?\DateTimeInterface $startedAt = null): void
    {
        $startedAt ??= new \DateTime();
        $rdv->setHeureDebutTravail($startedAt);
        $rdv->setHeureFinTravail(null);
    }

    public function finalizeWorkSession(RendezVous $rdv, ?\DateTimeInterface $endedAt = null): void
    {
        $endedAt ??= new \DateTime();
        $rdv->setHeureFinTravail($endedAt);

        $startedAt = $rdv->getHeureDebutTravail();
        if (!$startedAt) {
            return;
        }

        $diff = $startedAt->diff($endedAt);
        $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        if ($minutes > 0) {
            $rdv->setTempsEffectifMinutes(($rdv->getTempsEffectifMinutes() ?? 0) + $minutes);
        }
    }

    public function handleTransitionSideEffects(
        RendezVous $rdv,
        string $transitionName,
        array $data = [],
        ?int $userId = null,
    ): ?AnnulationRdv {
        return match ($transitionName) {
            'start_travail', 'reprendre_apres_pieces', 'reprendre_demain', 'retour_garantie', 'sortir_gardiennage' => $this->handleStartLikeTransition($rdv),
            'terminer', 'attendre_pieces', 'mettre_en_attente_reprise', 'passer_gardiennage', 'restituer_partiel' => $this->handleStopLikeTransition($rdv),
            'annuler', 'declarer_no_show' => $this->recordCancellation(
                $rdv,
                (string) ($data['motif'] ?? ''),
                (string) ($data['source'] ?? 'atelier'),
                isset($data['commentaire']) ? (string) $data['commentaire'] : null,
                $userId,
            ),
            default => null,
        };
    }

    private function handleStartLikeTransition(RendezVous $rdv): ?AnnulationRdv
    {
        $this->startWorkSession($rdv);
        return null;
    }

    private function handleStopLikeTransition(RendezVous $rdv): ?AnnulationRdv
    {
        $this->finalizeWorkSession($rdv);
        return null;
    }
}
