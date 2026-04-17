<?php

namespace App\Controller;

use App\Entity\ClauseLegale;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClauseLegaleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit,
    ) {}

    #[Route('/api/clauses-legales', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $activeOnly = $request->query->getBoolean('active', true);

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(ClauseLegale::class, 'c')
            ->orderBy('c.code', 'ASC')
            ->addOrderBy('c.version', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = true');
        }

        $clauses = $qb->getQuery()->getResult();

        return $this->json(array_map(fn($c) => $this->serialize($c), $clauses));
    }

    #[Route('/api/clauses-legales/{code}/active', methods: ['GET'])]
    public function getActive(string $code): JsonResponse
    {
        if (!in_array($code, ClauseLegale::CODES, true)) {
            return $this->json(['error' => 'Code invalide', 'allowed' => ClauseLegale::CODES], Response::HTTP_BAD_REQUEST);
        }

        $clause = $this->em->getRepository(ClauseLegale::class)->findOneBy(
            ['code' => $code, 'isActive' => true],
            ['version' => 'DESC'],
        );

        if (!$clause) {
            return $this->json(['error' => 'Aucune clause active pour ce code'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($clause));
    }

    #[Route('/api/clauses-legales', methods: ['POST'])]
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

        // Determine next version
        $maxVersion = $this->em->createQueryBuilder()
            ->select('MAX(c.version)')
            ->from(ClauseLegale::class, 'c')
            ->where('c.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleScalarResult();

        $clause = new ClauseLegale();
        $clause->setCode($code);
        $clause->setLibelle($data['libelle']);
        $clause->setTexte($data['texte']);
        $clause->setVersion(($maxVersion ?? 0) + 1);

        if (isset($data['effectiveFrom'])) {
            $clause->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        }

        $this->em->persist($clause);
        $this->em->flush();

        $this->audit->log('create_clause_legale', 'clause_legale', $clause->getId());

        return $this->json($this->serialize($clause), Response::HTTP_CREATED);
    }

    #[Route('/api/clauses-legales/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $clause = $this->em->getRepository(ClauseLegale::class)->find($id);
        if (!$clause) {
            return $this->json(['error' => 'Clause not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // If texte changed, create new version instead of modifying
        if (isset($data['texte']) && $data['texte'] !== $clause->getTexte()) {
            // Deactivate current
            $clause->setIsActive(false);
            $this->em->flush();

            // Create new version
            $newClause = new ClauseLegale();
            $newClause->setCode($clause->getCode());
            $newClause->setLibelle($data['libelle'] ?? $clause->getLibelle());
            $newClause->setTexte($data['texte']);
            $newClause->setVersion($clause->getVersion() + 1);
            $newClause->setAtelierId($clause->getAtelierId());

            if (isset($data['effectiveFrom'])) {
                $newClause->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
            }

            $this->em->persist($newClause);
            $this->em->flush();

            $this->audit->log('update_clause_legale_new_version', 'clause_legale', $newClause->getId(), json_encode([
                'previous_version' => $clause->getVersion(),
            ]));

            return $this->json($this->serialize($newClause));
        }

        // Only metadata update (libelle, isActive)
        if (isset($data['libelle'])) $clause->setLibelle($data['libelle']);
        if (isset($data['isActive'])) $clause->setIsActive((bool) $data['isActive']);

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
            $clause = $this->em->getRepository(ClauseLegale::class)->findOneBy(
                ['code' => $code, 'isActive' => true],
                ['version' => 'DESC'],
            );
            if ($clause) {
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
