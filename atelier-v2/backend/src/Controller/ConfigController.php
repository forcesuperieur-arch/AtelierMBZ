<?php
namespace App\Controller;

use App\Entity\ConfigAtelier;
use App\Entity\HoraireAtelier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/config')]
class ConfigController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy([]);
        if (!$config) {
            return $this->json(['error' => 'No configuration found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($this->serializer->serialize($config, 'json', ['groups' => ['config:read']]), true);
        return $this->json($data);
    }

    #[Route('', methods: ['PUT'])]
    public function updateConfig(Request $request): JsonResponse
    {
        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy([]);
        if (!$config) {
            return $this->json(['error' => 'No configuration found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['taux_horaire_mo_standard'])) $config->setTauxHoraireMoStandard($data['taux_horaire_mo_standard']);
        if (isset($data['taux_horaire_mo_complexe'])) $config->setTauxHoraireMoComplexe($data['taux_horaire_mo_complexe']);
        if (isset($data['taux_horaire_mo_expert'])) $config->setTauxHoraireMoExpert($data['taux_horaire_mo_expert']);
        if (isset($data['marge_pieces_standard'])) $config->setMargePiecesStandard($data['marge_pieces_standard']);
        if (isset($data['marge_pieces_consommable'])) $config->setMargePiecesConsommable($data['marge_pieces_consommable']);
        if (isset($data['marge_pieces_pneumatique'])) $config->setMargePiecesPneumatique($data['marge_pieces_pneumatique']);
        if (isset($data['forfait_mo_minimum'])) $config->setForfaitMoMinimum($data['forfait_mo_minimum']);
        if (isset($data['tva_mo_taux'])) $config->setTvaMoTaux($data['tva_mo_taux']);
        if (isset($data['tva_pieces_taux'])) $config->setTvaPiecesTaux($data['tva_pieces_taux']);
        if (isset($data['validite_devis_jours'])) $config->setValiditeDevisJours($data['validite_devis_jours']);
        if (isset($data['accompte_pourcentage'])) $config->setAccomptePourcentage($data['accompte_pourcentage']);

        // Update horaires if provided
        if (isset($data['horaires']) && is_array($data['horaires'])) {
            foreach ($data['horaires'] as $horaireData) {
                $jour = $horaireData['jour_semaine'] ?? null;
                if ($jour === null) continue;

                $horaire = $this->em->getRepository(HoraireAtelier::class)->findOneBy([
                    'jourSemaine' => $jour,
                    'atelierId' => $config->getAtelierId(),
                ]);
                if (!$horaire) {
                    $horaire = new HoraireAtelier();
                    $horaire->setJourSemaine($jour);
                    $horaire->setAtelierId($config->getAtelierId());
                    $this->em->persist($horaire);
                }
                if (isset($horaireData['heure_ouverture'])) $horaire->setHeureOuverture($horaireData['heure_ouverture']);
                if (isset($horaireData['heure_fermeture'])) $horaire->setHeureFermeture($horaireData['heure_fermeture']);
                if (isset($horaireData['pause_debut'])) $horaire->setPauseDebut($horaireData['pause_debut']);
                if (isset($horaireData['pause_fin'])) $horaire->setPauseFin($horaireData['pause_fin']);
                if (isset($horaireData['is_ouvert'])) $horaire->setIsOuvert($horaireData['is_ouvert']);
            }
        }

        $this->em->flush();

        $result = json_decode($this->serializer->serialize($config, 'json', ['groups' => ['config:read']]), true);
        return $this->json($result);
    }

    #[Route('/horaires', methods: ['GET'])]
    public function getHoraires(): JsonResponse
    {
        $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy([]);
        $atelierId = $config?->getAtelierId();

        $horaires = $this->em->getRepository(HoraireAtelier::class)->findBy(
            $atelierId ? ['atelierId' => $atelierId] : [],
            ['jourSemaine' => 'ASC']
        );

        $data = json_decode($this->serializer->serialize($horaires, 'json', ['groups' => ['horaire:read']]), true);
        return $this->json($data);
    }
}
