<?php

namespace App\Controller;

use App\Entity\ClauseLegale;
use App\Service\AuditService;
use App\Service\ClauseLegaleVisibilityService;
use App\Service\CurrentAtelierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ClauseLegaleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit,
        private CurrentAtelierResolver $currentAtelierResolver,
        private ClauseLegaleVisibilityService $visibilityService,
    ) {}

    #[Route('/api/clauses-legales', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $activeOnly = $request->query->getBoolean('active', true);
        $clauses = $this->visibilityService->pickVisibleClauses($this->findScopedClauses(), $activeOnly);

        return $this->json(array_map(fn($c) => $this->serialize($c), $clauses));
    }

    #[Route('/api/clauses-legales/{code}/active', methods: ['GET'])]
    public function getActive(string $code): JsonResponse
    {
        if (!in_array($code, ClauseLegale::CODES, true)) {
            return $this->json(['error' => 'Code invalide', 'allowed' => ClauseLegale::CODES], Response::HTTP_BAD_REQUEST);
        }

        $clause = $this->visibilityService->pickPreferredClause($this->findScopedClauses($code));

        if (!$clause || !$clause->isActive()) {
            return $this->json(['error' => 'Aucune clause active pour ce code'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($clause));
    }

    #[Route('/api/clauses-legales', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $code = $data['code'] ?? null;
        if (!$code || !in_array($code, ClauseLegale::CODES, true)) {
            return $this->json(['error' => 'Code invalide', 'allowed' => ClauseLegale::CODES], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['libelle']) || empty($data['texte'])) {
            return $this->json(['error' => 'libelle et texte requis'], Response::HTTP_BAD_REQUEST);
        }

        $atelierId = $this->currentAtelierResolver->resolveAtelierId();
        if ($atelierId === null) {
            return $this->json(['error' => 'Aucun atelier actif sélectionné'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $clause = $this->em->wrapInTransaction(function() use ($code, $data, $atelierId) {
                $c = new ClauseLegale();
                $c->setCode($code);
                $c->setLibelle($data['libelle']);
                $c->setTexte($data['texte']);
                $c->setVersion($this->getNextVersionForScope($code, $atelierId));
                $c->setAtelierId($atelierId);
                $c->setIsActive((bool) ($data['isActive'] ?? true));

                if (isset($data['effectiveFrom'])) {
                    $c->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
                }

                $this->em->persist($c);
                $this->em->flush();
                
                return $c;
            });
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
             return $this->json(['error' => 'Conflit de version, veuillez réessayer'], Response::HTTP_CONFLICT);
        }

        $this->audit->log('create_clause_legale', 'clause_legale', $clause->getId(), json_encode([
            'atelier_id' => $atelierId,
            'code' => $code,
        ]));

        return $this->json($this->serialize($clause), Response::HTTP_CREATED);
    }

    #[Route('/api/clauses-legales/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $clause = $this->runWithoutTenantFilter(
            fn () => $this->em->getRepository(ClauseLegale::class)->find($id)
        );

        if (!$clause) {
            return $this->json(['error' => 'Clause not found'], Response::HTTP_NOT_FOUND);
        }

        $currentAtelierId = $this->currentAtelierResolver->resolveAtelierId();
        if ($currentAtelierId === null) {
            return $this->json(['error' => 'Aucun atelier actif sélectionné'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $editingScopedOverride = $clause->getAtelierId() !== $currentAtelierId;

        if ($editingScopedOverride) {
            try {
                $newClause = $this->em->wrapInTransaction(function() use ($clause, $currentAtelierId, $data) {
                    $c = new ClauseLegale();
                    $c->setCode($clause->getCode());
                    $c->setLibelle($data['libelle'] ?? $clause->getLibelle());
                    $c->setTexte($data['texte'] ?? $clause->getTexte());
                    $c->setVersion($this->getNextVersionForScope($clause->getCode(), $currentAtelierId));
                    $c->setAtelierId($currentAtelierId);
                    $c->setIsActive((bool) ($data['isActive'] ?? $clause->isActive()));
                    $c->setEffectiveFrom(isset($data['effectiveFrom']) ? new \DateTime($data['effectiveFrom']) : \DateTimeImmutable::createFromInterface($clause->getEffectiveFrom()));

                    $this->em->persist($c);
                    $this->em->flush();
                    return $c;
                });
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                 return $this->json(['error' => 'Conflit de version, veuillez réessayer'], Response::HTTP_CONFLICT);
            }

            $this->audit->log('override_clause_legale', 'clause_legale', $newClause->getId(), json_encode([
                'source_clause_id' => $clause->getId(),
                'atelier_id' => $currentAtelierId,
            ]));

            return $this->json($this->serialize($newClause));
        }

        if (isset($data['texte']) && $data['texte'] !== $clause->getTexte()) {
            $clause->setIsActive(false);

            $newClause = new ClauseLegale();
            $newClause->setCode($clause->getCode());
            $newClause->setLibelle($data['libelle'] ?? $clause->getLibelle());
            $newClause->setTexte($data['texte']);
            $newClause->setVersion($this->getNextVersionForScope($clause->getCode(), $currentAtelierId));
            $newClause->setAtelierId($currentAtelierId);
            $newClause->setIsActive((bool) ($data['isActive'] ?? true));
            $newClause->setEffectiveFrom(isset($data['effectiveFrom']) ? new \DateTime($data['effectiveFrom']) : \DateTimeImmutable::createFromInterface($clause->getEffectiveFrom()));

            $this->em->persist($newClause);
            $this->em->flush();

            $this->audit->log('update_clause_legale_new_version', 'clause_legale', $newClause->getId(), json_encode([
                'previous_version' => $clause->getVersion(),
                'atelier_id' => $currentAtelierId,
            ]));

            return $this->json($this->serialize($newClause));
        }

        if (isset($data['libelle'])) {
            $clause->setLibelle($data['libelle']);
        }
        if (isset($data['isActive'])) {
            $clause->setIsActive((bool) $data['isActive']);
        }
        if (isset($data['effectiveFrom'])) {
            $clause->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        }

        $this->em->flush();

        return $this->json($this->serialize($clause));
    }

    #[Route('/api/clauses-legales/hash', methods: ['POST'])]
    public function hashActiveClauses(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $codes = $data['codes'] ?? ClauseLegale::CODES;

        $clauses = [];
        foreach ($codes as $code) {
            $clause = $this->visibilityService->pickPreferredClause($this->findScopedClauses($code));
            if ($clause && $clause->isActive()) {
                $clauses[$code] = [
                    'version' => $clause->getVersion(),
                    'hash' => hash('sha256', $clause->getTexte()),
                ];
            }
        }

        return $this->json([
            'clauses' => $clauses,
            'globalHash' => hash('sha256', json_encode($clauses)),
        ]);
    }

    /**
     * @return ClauseLegale[]
     */
    private function findScopedClauses(?string $code = null): array
    {
        $atelierId = $this->currentAtelierResolver->resolveAtelierId();

        return $this->runWithoutTenantFilter(function () use ($atelierId, $code) {
            $qb = $this->em->createQueryBuilder()
                ->select('c')
                ->from(ClauseLegale::class, 'c')
                ->orderBy('c.code', 'ASC')
                ->addOrderBy('c.version', 'DESC');

            if ($code !== null) {
                $qb->andWhere('c.code = :code')
                    ->setParameter('code', $code);
            }

            if ($atelierId !== null) {
                $qb->andWhere('c.atelierId = :atelierId OR c.atelierId IS NULL')
                    ->setParameter('atelierId', $atelierId);
            } else {
                $qb->andWhere('c.atelierId IS NULL');
            }

            return $qb->getQuery()->getResult();
        });
    }

    private function getNextVersionForScope(string $code, ?int $atelierId): int
    {
        $maxVersion = $this->runWithoutTenantFilter(function () use ($code, $atelierId) {
            $qb = $this->em->createQueryBuilder()
                ->select('MAX(c.version)')
                ->from(ClauseLegale::class, 'c')
                ->where('c.code = :code')
                ->setParameter('code', $code);

            if ($atelierId !== null) {
                $qb->andWhere('c.atelierId = :atelierId')
                    ->setParameter('atelierId', $atelierId);
            } else {
                $qb->andWhere('c.atelierId IS NULL');
            }

            return $qb->getQuery()->getSingleScalarResult();
        });

        return ((int) ($maxVersion ?? 0)) + 1;
    }

    private function runWithoutTenantFilter(callable $callback): mixed
    {
        $filters = $this->em->getFilters();
        $wasEnabled = $filters->isEnabled('tenant_filter');

        if ($wasEnabled) {
            $filters->disable('tenant_filter');
        }

        try {
            return $callback();
        } finally {
            if ($wasEnabled) {
                $restoredFilter = $filters->enable('tenant_filter');
                $atelierId = $this->currentAtelierResolver->resolveAtelierId();
                if ($atelierId !== null) {
                    $restoredFilter->setParameter('atelier_id', $atelierId);
                }
            }
        }
    }

    private function serialize(ClauseLegale $c): array
    {
        return [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'libelle' => $c->getLibelle(),
            'texte' => $c->getTexte(),
            'version' => $c->getVersion(),
            'effectiveFrom' => $c->getEffectiveFrom()->format('Y-m-d'),
            'isActive' => $c->isActive(),
            'createdAt' => $c->getCreatedAt()->format('c'),
        ];
    }
}
