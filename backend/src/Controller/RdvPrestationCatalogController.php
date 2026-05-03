<?php

namespace App\Controller;

use App\Entity\CategorieMoto;
use App\Entity\Prestation;
use App\Entity\Vehicule;
use App\Service\AtelierCatalogBootstrapService;
use App\Service\CurrentAtelierResolver;
use App\Service\PrestationCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RdvPrestationCatalogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CurrentAtelierResolver $currentAtelierResolver,
        private PrestationCatalogService $catalogService,
        private AtelierCatalogBootstrapService $atelierCatalogBootstrapService,
    ) {
    }

    #[Route('/api/rdv/prestations-catalogue', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json([], Response::HTTP_OK);
        }

        $this->atelierCatalogBootstrapService->ensurePrestationsForAtelier($atelierId);

        $vehicule = (new Vehicule())
            ->setPlaque('RDV-TEMP')
            ->setAtelierId($atelierId)
            ->setTypeMoto($request->query->get('type_moto') ?: null)
            ->setCylindree($request->query->get('cylindree') ?: null);

        $categorieId = (int) $request->query->get('categorie_id', 0);
        if ($categorieId > 0) {
            $categorie = $this->em->getRepository(CategorieMoto::class)->find($categorieId);
            if ($categorie instanceof CategorieMoto) {
                $vehicule->setCategorie($categorie);
            }
        }

        $entries = $this->catalogService->getApplicablePrestations($vehicule);

        $payload = array_map(function (array $entry) use ($atelierId): array {
            /** @var Prestation $prestation */
            $prestation = $entry['prestation'];

            return [
                'id' => $prestation->getId(),
                'code' => $prestation->getCode(),
                'nom' => $prestation->getNom(),
                'description' => $prestation->getDescription(),
                'categorie' => $prestation->getCategorie(),
                'type_vehicule' => $prestation->getTypeVehicule(),
                'cylindree_min' => $prestation->getCylindreeMin(),
                'cylindree_max' => $prestation->getCylindreeMax(),
                'type_tarif' => $prestation->getTypeTarif(),
                'is_active' => $prestation->getIsActive(),
                'prix_base_ht' => (float) ($entry['prix_ht'] ?? $prestation->getPrixBaseHt()),
                'prix_base_ttc' => (float) ($entry['prix_ttc'] ?? $prestation->getPrixBaseTtc()),
                'temps_estime_minutes' => (int) ($entry['temps_minutes'] ?? $prestation->getTempsEstimeMinutes()),
                'price_source' => $entry['source'] ?? 'unknown',
                'atelier_id' => $atelierId,
            ];
        }, $entries);

        return $this->json($payload);
    }
}
