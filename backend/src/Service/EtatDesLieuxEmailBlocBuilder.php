<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EtatDesLieux;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Construit le bloc HTML optionnel {{etat_des_lieux_bloc}} de l'email
 * « travaux terminés » (Lot B).
 *
 * Retourne une chaîne VIDE si aucun état des lieux signé : les call sites
 * (RdvWorkflowListener::notifyClient et MecanicienController::signMecanicien)
 * doivent TOUJOURS passer la variable, car NotificationTemplate::render() est
 * un str_replace naïf — une variable absente resterait affichée
 * '{{etat_des_lieux_bloc}}' en clair dans l'email envoyé.
 */
class EtatDesLieuxEmailBlocBuilder
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {}

    public function build(RendezVous $rdv): string
    {
        $etatDesLieux = $this->em->getRepository(EtatDesLieux::class)->findOneBy(['rendezVous' => $rdv]);
        if (!$etatDesLieux || !$etatDesLieux->isSigned()) {
            return '';
        }

        // Pattern Lot A (PublicBookingController::sendBookingAcknowledgement) :
        // PUBLIC_URL prioritaire, fallback sur l'hôte de la requête courante.
        $baseUrl = rtrim(
            $_ENV['PUBLIC_URL'] ?? $this->requestStack->getCurrentRequest()?->getSchemeAndHttpHost() ?? '',
            '/',
        );
        $url = $baseUrl . '/client/rdvs/' . $rdv->getId();

        return sprintf(
            '<p>L\'état des lieux d\'entrée de votre véhicule est consultable dans votre espace client : <a href="%s">Voir l\'état des lieux</a></p>',
            htmlspecialchars($url),
        );
    }
}
