<?php

namespace App\Service;

use App\Entity\GrilleTarifaire;
use App\Entity\Prestation;
use Doctrine\ORM\EntityManagerInterface;

final class AtelierCatalogBootstrapService
{
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
                return 0;
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
