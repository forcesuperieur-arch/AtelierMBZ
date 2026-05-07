<?php

namespace App\Service;

use App\Entity\GrilleTarifaire;
use App\Entity\Prestation;
use Doctrine\ORM\EntityManagerInterface;

final class AtelierCatalogBootstrapService
{
    /**
     * Catalogue de référence utilisé en fallback quand aucun atelier-source
     * n'existe encore (premier atelier créé avant app:seed).
     * Format : [code, nom, categorie, prixHt, prixTtc, tempsMinutes, description, typeVehicule, typeTarif]
     */
    private const DEFAULT_CATALOG = [
        ['DIAG-45',    'Diagnostic / recherche de panne',     'diagnostic',    '49.17', '59.00',  45,  'Lecture défauts, contrôle visuel et premier diagnostic.',              'tous',   'forfait'],
        ['VID-SCOOT',  'Forfait vidange scooter',             'entretien',     '57.50', '69.00',  45,  'Vidange moteur avec contrôle des niveaux et serrages.',                'scooter','forfait'],
        ['VID-MOTO',   'Forfait vidange moto',                'entretien',     '74.17', '89.00',  60,  'Vidange standard et contrôle sécurité atelier.',                       'moto',   'forfait'],
        ['REV-INT',    'Révision intermédiaire',              'entretien',     '107.50','129.00', 90,  'Contrôle des points de sécurité et entretien courant.',                'tous',   'forfait'],
        ['REV-CPL',    'Révision complète',                   'entretien',     '182.50','219.00', 180, 'Révision atelier complète avec essai et vérifications.',               'tous',   'forfait'],
        ['PNEU-AV',    'Forfait pneu avant',                  'pneumatique',   '29.17', '35.00',  30,  'Montage et équilibrage du pneu avant.',                                'moto',   'forfait'],
        ['PNEU-AR',    'Forfait pneu arrière',                'pneumatique',   '32.50', '39.00',  35,  'Montage et équilibrage du pneu arrière.',                              'moto',   'forfait'],
        ['PNEU-SET',   'Forfait train de pneus',              'pneumatique',   '62.50', '75.00',  75,  'Montage et équilibrage avant + arrière.',                              'moto',   'forfait'],
        ['FREIN-AV',   'Forfait plaquettes avant',            'freinage',      '57.50', '69.00',  60,  'Remplacement plaquettes avant et contrôle du circuit.',                'tous',   'forfait'],
        ['FREIN-AR',   'Forfait plaquettes arrière',          'freinage',      '49.17', '59.00',  45,  'Remplacement plaquettes arrière et contrôle.',                         'tous',   'forfait'],
        ['KIT-CHAINE', 'Forfait kit chaîne',                  'transmission',  '99.17', '119.00', 75,  'Pose du kit chaîne avec réglage tension et alignement.',               'moto',   'forfait'],
        ['BATTERIE',   'Forfait batterie / charge',           'electricite',   '40.83', '49.00',  30,  'Contrôle charge, tension et remplacement simple.',                     'tous',   'forfait'],
        ['HIVER',      'Forfait hivernage / remise en route', 'saisonnier',    '65.83', '79.00',  60,  'Contrôle complet après immobilisation et remise en route.',            'tous',   'forfait'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function ensurePrestationsForAtelier(?int $atelierId): int
    {
        if (!$atelierId || $atelierId <= 0) {
            return 0;
        }

        $filters = $this->em->getFilters();
        $tenantWasEnabled = $filters->isEnabled('tenant_filter');

        if ($tenantWasEnabled) {
            $filters->disable('tenant_filter');
        }

        try {
            $sourceAtelierId = $this->resolveSourceAtelierId($atelierId);
            if (!$sourceAtelierId) {
                return $this->createFromDefaultCatalog($atelierId);
            }

            $prestationRepo = $this->em->getRepository(Prestation::class);
            $grilleRepo = $this->em->getRepository(GrilleTarifaire::class);
            $sourcePrestations = $prestationRepo->findBy(['atelierId' => $sourceAtelierId], ['id' => 'ASC']);

            if ($sourcePrestations === []) {
                return 0;
            }

            $currentPrestations = $prestationRepo->findBy(['atelierId' => $atelierId], ['id' => 'ASC']);
            $prestationMap = [];
            $created = 0;

            if ($currentPrestations === []) {
                foreach ($sourcePrestations as $source) {
                    if (!$source instanceof Prestation) {
                        continue;
                    }

                    $clone = (new Prestation())
                        ->setAtelierId($atelierId)
                        ->setCode($this->buildUniqueCode($source->getCode(), $atelierId))
                        ->setNom($source->getNom())
                        ->setDescription($source->getDescription())
                        ->setCategorie($source->getCategorie())
                        ->setSousCategorie($source->getSousCategorie())
                        ->setPrixBaseHt($source->getPrixBaseHt())
                        ->setPrixBaseTtc($source->getPrixBaseTtc())
                        ->setTempsEstimeMinutes($source->getTempsEstimeMinutes())
                        ->setDelaiInterventionJours($source->getDelaiInterventionJours())
                        ->setTypeTarif($source->getTypeTarif())
                        ->setTauxHoraireApplique($source->getTauxHoraireApplique())
                        ->setTypeVehicule($source->getTypeVehicule())
                        ->setCylindreeMin($source->getCylindreeMin())
                        ->setCylindreeMax($source->getCylindreeMax())
                        ->setIsActive($source->getIsActive())
                        ->setIsForfait($source->getIsForfait())
                        ->setIsPromo($source->getIsPromo())
                        ->setPrixPromoTtc($source->getPrixPromoTtc())
                        ->setInclutPieces($source->getInclutPieces())
                        ->setMargePiecesPourcent($source->getMargePiecesPourcent())
                        ->setGarantieJours($source->getGarantieJours())
                        ->setNecessiteEssai($source->getNecessiteEssai());

                    $this->em->persist($clone);
                    $prestationMap[(int) $source->getId()] = $clone;
                    $created++;
                }

                $this->em->flush();
                $currentPrestations = $prestationRepo->findBy(['atelierId' => $atelierId], ['id' => 'ASC']);
            }

            if ($prestationMap === []) {
                $currentBySignature = [];
                foreach ($currentPrestations as $current) {
                    if ($current instanceof Prestation) {
                        $currentBySignature[$this->buildSignature($current)] = $current;
                    }
                }

                foreach ($sourcePrestations as $source) {
                    if (!$source instanceof Prestation) {
                        continue;
                    }

                    $signature = $this->buildSignature($source);
                    if (isset($currentBySignature[$signature])) {
                        $prestationMap[(int) $source->getId()] = $currentBySignature[$signature];
                    }
                }
            }

            if ($grilleRepo->count(['atelierId' => $atelierId]) === 0 && $prestationMap !== []) {
                $sourceGrilles = $grilleRepo->findBy(['atelierId' => $sourceAtelierId], ['id' => 'ASC']);

                foreach ($sourceGrilles as $sourceGrille) {
                    if (!$sourceGrille instanceof GrilleTarifaire) {
                        continue;
                    }

                    $sourcePrestationId = $sourceGrille->getPrestation()->getId();
                    if (!$sourcePrestationId || !isset($prestationMap[$sourcePrestationId])) {
                        continue;
                    }

                    $cloneGrille = (new GrilleTarifaire())
                        ->setAtelierId($atelierId)
                        ->setPrestation($prestationMap[$sourcePrestationId])
                        ->setCategorieMoto($sourceGrille->getCategorieMoto())
                        ->setTypeVehicule($sourceGrille->getTypeVehicule())
                        ->setCylindreeMin($sourceGrille->getCylindreeMin())
                        ->setCylindreeMax($sourceGrille->getCylindreeMax())
                        ->setPrixHt($sourceGrille->getPrixHt())
                        ->setPrixTtc($sourceGrille->getPrixTtc())
                        ->setTempsMinutes($sourceGrille->getTempsMinutes())
                        ->setTypeTarif($sourceGrille->getTypeTarif())
                        ->setDelaiJours($sourceGrille->getDelaiJours())
                        ->setIsActive($sourceGrille->getIsActive());

                    $this->em->persist($cloneGrille);
                    $created++;
                }

                $this->em->flush();
            }

            return $created;
        } finally {
            if ($tenantWasEnabled) {
                $filters->enable('tenant_filter');
            }
        }
    }

    private function createFromDefaultCatalog(int $atelierId): int
    {
        $repo = $this->em->getRepository(Prestation::class);

        if ($repo->count(['atelierId' => $atelierId]) > 0) {
            return 0;
        }

        $created = 0;
        foreach (self::DEFAULT_CATALOG as [$code, $nom, $categorie, $prixHt, $prixTtc, $temps, $description, $typeVehicule, $typeTarif]) {
            $p = (new Prestation())
                ->setAtelierId($atelierId)
                ->setCode($this->buildUniqueCode($code, $atelierId))
                ->setNom($nom)
                ->setDescription($description)
                ->setCategorie($categorie)
                ->setPrixBaseHt($prixHt)
                ->setPrixBaseTtc($prixTtc)
                ->setTempsEstimeMinutes($temps)
                ->setTypeTarif($typeTarif)
                ->setTypeVehicule($typeVehicule)
                ->setIsForfait('forfait' === $typeTarif ? 1 : 0)
                ->setIsActive(1);
            $this->em->persist($p);
            $created++;
        }

        $this->em->flush();

        return $created;
    }

    private function resolveSourceAtelierId(int $atelierId): ?int
    {
        $result = $this->em->createQueryBuilder()
            ->select('p.atelierId AS atelierId, COUNT(p.id) AS HIDDEN prestationCount')
            ->from(Prestation::class, 'p')
            ->where('p.atelierId IS NOT NULL')
            ->andWhere('p.atelierId != :atelierId')
            ->setParameter('atelierId', $atelierId)
            ->groupBy('p.atelierId')
            ->orderBy('prestationCount', 'DESC')
            ->addOrderBy('p.atelierId', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        $value = $result[0]['atelierId'] ?? null;
        $resolved = is_numeric($value) ? (int) $value : null;

        return $resolved && $resolved > 0 ? $resolved : null;
    }

    private function buildSignature(Prestation $prestation): string
    {
        return implode('|', [
            mb_strtolower(trim($prestation->getNom())),
            mb_strtolower(trim($prestation->getCategorie())),
            mb_strtolower(trim($prestation->getTypeVehicule())),
            (string) ($prestation->getTempsEstimeMinutes()),
        ]);
    }

    private function buildUniqueCode(string $baseCode, int $atelierId): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9_]+/i', '_', trim($baseCode)));
        $normalized = trim($normalized, '_');
        $normalized = $normalized !== '' ? $normalized : 'PRESTATION';

        $suffix = '_A' . $atelierId;
        $maxBaseLength = max(1, 50 - strlen($suffix));
        $candidate = substr($normalized, 0, $maxBaseLength) . $suffix;
        $index = 2;

        while ($this->em->getRepository(Prestation::class)->findOneBy(['code' => $candidate]) instanceof Prestation) {
            $extra = '_' . $index;
            $trimmedBase = substr($normalized, 0, max(1, 50 - strlen($suffix . $extra)));
            $candidate = $trimmedBase . $suffix . $extra;
            $index++;
        }

        return $candidate;
    }
}
