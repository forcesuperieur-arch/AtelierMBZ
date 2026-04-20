<?php
namespace App\Controller;

use App\Entity\Atelier;
use App\Entity\CategorieMoto;
use App\Entity\ConfigAtelier;
use App\Entity\GrilleTarifaire;
use App\Entity\HoraireAtelier;
use App\Entity\Prestation;
use App\Service\AdminConfigValidator;
use App\Service\AtelierCatalogBootstrapService;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/config')]
class ConfigController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private SluggerInterface $slugger,
        private AdminConfigValidator $configValidator,
        private CurrentAtelierResolver $currentAtelierResolver,
        private AtelierCatalogBootstrapService $atelierCatalogBootstrapService,
    ) {}

    #[Route('', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getConfig(): JsonResponse
    {
        $config = $this->findCurrentConfig(true);
        if (!$config) {
            return $this->json(['error' => 'No configuration found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($this->serializer->serialize($config, 'json', ['groups' => ['config:read']]), true);
        $atelier = $this->resolveAtelier($config);

        if ($atelier) {
            $data['atelier'] = $this->serializeAtelier($atelier);
        }

        $horaires = $this->em->getRepository(HoraireAtelier::class)->findBy(
            $config?->getAtelierId() ? ['atelierId' => $config->getAtelierId()] : [],
            ['jourSemaine' => 'ASC']
        );
        $data['horaires'] = json_decode($this->serializer->serialize($horaires, 'json', ['groups' => ['horaire:read']]), true);

        return $this->json($data);
    }

    #[Route('/prestations/bootstrap', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bootstrapPrestations(): JsonResponse
    {
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if (!$atelierId) {
            return $this->json(['error' => 'Contexte atelier introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $created = $this->atelierCatalogBootstrapService->ensurePrestationsForAtelier($atelierId);

        return $this->json([
            'success' => true,
            'atelier_id' => $atelierId,
            'created' => $created,
        ]);
    }

    #[Route('', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateConfig(Request $request): JsonResponse
    {
        $config = $this->findCurrentConfig(true);
        if (!$config) {
            return $this->json(['error' => 'No configuration found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $configData = isset($data['config']) && is_array($data['config']) ? $data['config'] : $data;
        $atelierData = isset($data['atelier']) && is_array($data['atelier']) ? $data['atelier'] : [];

        $errors = $this->configValidator->validateConfigPayload($configData, $data['horaires'] ?? []);
        if ($errors !== []) {
            return $this->json([
                'error' => 'Configuration invalide',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($configData['taux_horaire_mo_standard'])) $config->setTauxHoraireMoStandard($configData['taux_horaire_mo_standard']);
        if (isset($configData['taux_horaire_mo_complexe'])) $config->setTauxHoraireMoComplexe($configData['taux_horaire_mo_complexe']);
        if (isset($configData['taux_horaire_mo_expert'])) $config->setTauxHoraireMoExpert($configData['taux_horaire_mo_expert']);
        if (isset($configData['marge_pieces_standard'])) $config->setMargePiecesStandard($configData['marge_pieces_standard']);
        if (isset($configData['marge_pieces_consommable'])) $config->setMargePiecesConsommable($configData['marge_pieces_consommable']);
        if (isset($configData['marge_pieces_pneumatique'])) $config->setMargePiecesPneumatique($configData['marge_pieces_pneumatique']);
        if (isset($configData['forfait_mo_minimum'])) $config->setForfaitMoMinimum($configData['forfait_mo_minimum']);
        if (isset($configData['tva_mo_taux'])) $config->setTvaMoTaux($configData['tva_mo_taux']);
        if (isset($configData['tva_pieces_taux'])) $config->setTvaPiecesTaux($configData['tva_pieces_taux']);
        if (isset($configData['validite_devis_jours'])) $config->setValiditeDevisJours($configData['validite_devis_jours']);
        if (isset($configData['accompte_pourcentage'])) $config->setAccomptePourcentage($configData['accompte_pourcentage']);
        if (isset($configData['garantie_travaux_jours'])) $config->setGarantieTravauxJours((int) $configData['garantie_travaux_jours']);
        if (isset($configData['tarif_gardiennage_journalier'])) $config->setTarifGardiennageJournalier((string) $configData['tarif_gardiennage_journalier']);
        if (isset($configData['jours_fermeture_hebdo']) && is_array($configData['jours_fermeture_hebdo'])) $config->setJoursFermetureHebdo(array_values($configData['jours_fermeture_hebdo']));
        if (isset($configData['dates_fermeture_exceptionnelles']) && is_array($configData['dates_fermeture_exceptionnelles'])) $config->setDatesFermetureExceptionnelles(array_values($configData['dates_fermeture_exceptionnelles']));
        if (
            isset($configData['feature_modules'])
            && is_array($configData['feature_modules'])
            && $this->isGranted('ROLE_SUPER_ADMIN')
        ) {
            $config->setFeatureModules($configData['feature_modules']);
        }

        $atelier = $this->resolveAtelier($config);
        if ($atelier && $atelierData) {
            if (isset($atelierData['nom']) && trim((string) $atelierData['nom']) !== '') $atelier->setNom(trim((string) $atelierData['nom']));
            if (isset($atelierData['adresse'])) $atelier->setAdresse($atelierData['adresse'] ?: null);
            if (isset($atelierData['cp'])) $atelier->setCp($atelierData['cp'] ?: null);
            if (isset($atelierData['ville'])) $atelier->setVille($atelierData['ville'] ?: null);
            if (isset($atelierData['telephone'])) $atelier->setTelephone($atelierData['telephone'] ?: null);
            if (isset($atelierData['email'])) $atelier->setEmail($atelierData['email'] ?: null);
            if (isset($atelierData['siret'])) $atelier->setSiret($atelierData['siret'] ?: null);
            if (isset($atelierData['tva_intracom'])) $atelier->setTvaIntracom($atelierData['tva_intracom'] ?: null);

            $baseName = trim((string) ($atelierData['nom'] ?? $atelier->getNom() ?? 'atelier-principal'));
            $atelier->setSlug(strtolower((string) $this->slugger->slug($baseName ?: 'atelier-principal')));
        }

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
                if (isset($horaireData['is_ouvert'])) $horaire->setIsOuvert((bool) $horaireData['is_ouvert']);
            }
        }

        $this->em->flush();

        $result = json_decode($this->serializer->serialize($config, 'json', ['groups' => ['config:read']]), true);
        if ($atelier) {
            $result['atelier'] = $this->serializeAtelier($atelier);
        }

        $horaires = $this->em->getRepository(HoraireAtelier::class)->findBy(
            $config?->getAtelierId() ? ['atelierId' => $config->getAtelierId()] : [],
            ['jourSemaine' => 'ASC']
        );
        $result['horaires'] = json_decode($this->serializer->serialize($horaires, 'json', ['groups' => ['horaire:read']]), true);

        return $this->json($result);
    }

    #[Route('/logo', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadLogo(Request $request): JsonResponse
    {
        $config = $this->findCurrentConfig(true);
        if (!$config) {
            return $this->json(['error' => 'No configuration found'], Response::HTTP_NOT_FOUND);
        }

        $atelier = $this->resolveAtelier($config);
        if (!$atelier) {
            return $this->json(['error' => 'No workshop found'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('logo') ?? $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Logo file required'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['error' => 'Only JPEG, PNG and WebP are allowed (SVG refused for security)'], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['error' => 'File too large (max 5MB)'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = strtolower((string) $this->slugger->slug($originalName ?: 'logo-atelier'));
        $extension = $file->guessExtension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', $safeName ?: 'logo-atelier', uniqid(), $extension);

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/logos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $file->move($uploadDir, $filename);
        $atelier->setLogoUrl('/uploads/logos/' . $filename);
        $this->em->flush();

        return $this->json([
            'logo_url' => $atelier->getLogoUrl(),
            'atelier' => $this->serializeAtelier($atelier),
        ], Response::HTTP_CREATED);
    }

    #[Route('/seed-tarifs', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function seedTarifs(): JsonResponse
    {
        $config = $this->findCurrentConfig(true);
        $atelierId = $config?->getAtelierId();

        $categories = $this->em->getRepository(CategorieMoto::class)->findBy(['isActive' => 1], ['nom' => 'ASC']);
        $prestations = $this->em->getRepository(Prestation::class)->findBy(['isActive' => 1], ['nom' => 'ASC']);

        if (!$categories || !$prestations) {
            return $this->json([
                'created' => 0,
                'error' => 'Prestations or moto categories are missing',
            ], Response::HTTP_BAD_REQUEST);
        }

        $existing = [];
        foreach ($this->em->getRepository(GrilleTarifaire::class)->findBy($atelierId ? ['atelierId' => $atelierId] : []) as $row) {
            $existing[$row->getPrestation()->getId() . '-' . ($row->getCategorieMoto()?->getId() ?? '0')] = true;
        }

        $created = 0;
        foreach ($categories as $category) {
            foreach ($prestations as $prestation) {
                $key = $prestation->getId() . '-' . $category->getId();
                if (isset($existing[$key])) {
                    continue;
                }

                $row = new GrilleTarifaire();
                $row->setAtelierId($atelierId);
                $row->setPrestation($prestation);
                $row->setCategorieMoto($category);
                $row->setTypeVehicule($prestation->getTypeVehicule() ?: 'tous');
                $row->setPrixHt((string) $prestation->getPrixBaseHt());
                $row->setPrixTtc((string) $prestation->getPrixBaseTtc());
                $row->setTempsMinutes((int) $prestation->getTempsEstimeMinutes());
                $row->setTypeTarif($prestation->getTypeTarif() ?: 'forfait');
                $row->setDelaiJours((int) $prestation->getDelaiInterventionJours());
                $row->setIsActive((int) $prestation->getIsActive());
                $this->em->persist($row);
                $created++;
            }
        }

        $this->em->flush();

        return $this->json([
            'created' => $created,
            'categories' => count($categories),
            'prestations' => count($prestations),
        ], Response::HTTP_CREATED);
    }

    #[Route('/horaires', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getHoraires(): JsonResponse
    {
        $config = $this->findCurrentConfig(true);
        $atelierId = $config?->getAtelierId();

        $horaires = $this->em->getRepository(HoraireAtelier::class)->findBy(
            $atelierId ? ['atelierId' => $atelierId] : [],
            ['jourSemaine' => 'ASC']
        );

        $data = json_decode($this->serializer->serialize($horaires, 'json', ['groups' => ['horaire:read']]), true);
        return $this->json($data);
    }

    private function resolveAtelier(?ConfigAtelier $config): ?Atelier
    {
        $atelierId = $config?->getAtelierId() ?? $this->currentAtelierResolver->resolveAtelierId();

        if ($atelierId) {
            return $this->em->getRepository(Atelier::class)->find($atelierId);
        }

        return null;
    }

    private function findCurrentConfig(bool $createIfMissing = false): ?ConfigAtelier
    {
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();

        if ($atelierId) {
            $config = $this->em->getRepository(ConfigAtelier::class)->findOneBy(['atelierId' => $atelierId]);
            if ($config instanceof ConfigAtelier) {
                return $config;
            }

            if ($createIfMissing) {
                $config = (new ConfigAtelier())->setAtelierId($atelierId);
                $this->em->persist($config);
                $this->em->flush();

                return $config;
            }
        }

        return null;
    }

    private function serializeAtelier(Atelier $atelier): array
    {
        return [
            'id' => $atelier->getId(),
            'nom' => $atelier->getNom(),
            'slug' => $atelier->getSlug(),
            'adresse' => $atelier->getAdresse(),
            'cp' => $atelier->getCp(),
            'ville' => $atelier->getVille(),
            'telephone' => $atelier->getTelephone(),
            'email' => $atelier->getEmail(),
            'siret' => $atelier->getSiret(),
            'tva_intracom' => $atelier->getTvaIntracom(),
            'logo_url' => $atelier->getLogoUrl(),
        ];
    }
}
