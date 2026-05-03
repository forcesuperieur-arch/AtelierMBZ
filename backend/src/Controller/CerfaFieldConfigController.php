<?php

namespace App\Controller;

use App\Entity\CerfaFieldConfig;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de la configuration des positions de champs CERFA.
 * ROLE_SUPER_ADMIN uniquement — les positions sont globales (pas multi-tenant).
 */
#[Route('/api/admin/cerfa-config')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CerfaFieldConfigController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private AuditService $audit,
    ) {}

    /** Liste tous les champs pour un cerfa_ref donné, ou tous si pas de filtre */
    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $requestedRef = $request->query->get('cerfa_ref');
        $this->ensureDefaultRows($requestedRef ? (string) $requestedRef : null);

        $qb = $this->em->getRepository(CerfaFieldConfig::class)->createQueryBuilder('f')
            ->orderBy('f.cerfaRef', 'ASC')
            ->addOrderBy('f.fieldKey', 'ASC');

        if ($ref = $requestedRef) {
            $qb->andWhere('f.cerfaRef = :ref')->setParameter('ref', $ref);
        }

        $fields = $qb->getQuery()->getResult();

        $data = $this->serializer->normalize($fields, null, ['groups' => 'cerfa_config:read']);

        return $this->json([
            'items' => $data,
            'cerfa_refs' => CerfaFieldConfig::CERFA_REFS,
        ]);
    }

    /** Met à jour x/y/width/fontSize/isActive d'un champ */
    #[Route('/{id}', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $field = $this->em->getRepository(CerfaFieldConfig::class)->find($id);
        if (!$field) {
            return $this->json(['error' => 'Champ introuvable'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (isset($payload['x']))         $field->setX((string) $payload['x']);
        if (isset($payload['y']))         $field->setY((string) $payload['y']);
        if (isset($payload['width']))     $field->setWidth((string) $payload['width']);
        if (isset($payload['font_size'])) $field->setFontSize((string) $payload['font_size']);
        if (isset($payload['char_box_width'])) $field->setCharBoxWidth((string) $payload['char_box_width']);
        if (isset($payload['char_gap'])) $field->setCharGap((string) $payload['char_gap']);
        if (isset($payload['date_group_gap'])) $field->setDateGroupGap((string) $payload['date_group_gap']);
        if (isset($payload['label']))     $field->setLabel((string) $payload['label']);
        if (isset($payload['is_active'])) $field->setIsActive((bool) $payload['is_active']);

        $this->em->flush();

        $this->audit->log(
            'cerfa_field_config.update',
            'CerfaFieldConfig',
            $field->getId(),
            sprintf('%s.%s', $field->getCerfaRef(), $field->getFieldKey()),
        );

        return $this->json(
            $this->serializer->normalize($field, null, ['groups' => 'cerfa_config:read'])
        );
    }

    /** Réinitialise un champ à ses valeurs d'origine hardcodées */
    #[Route('/{id}/reset', methods: ['POST'])]
    public function reset(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $field = $this->em->getRepository(CerfaFieldConfig::class)->find($id);
        if (!$field) {
            return $this->json(['error' => 'Champ introuvable'], Response::HTTP_NOT_FOUND);
        }

        $defaults = CerfaFieldConfigDefaults::getDefaults($field->getCerfaRef(), $field->getFieldKey());
        if (!$defaults) {
            return $this->json(['error' => 'Aucune valeur par défaut trouvée'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $field->setX($defaults['x']);
        $field->setY($defaults['y']);
        $field->setWidth($defaults['width']);
        $field->setFontSize($defaults['font_size']);
        $field->setCharBoxWidth($defaults['char_box_width'] ?? '0');
        $field->setCharGap($defaults['char_gap'] ?? '0');
        $field->setDateGroupGap($defaults['date_group_gap'] ?? '0');
        $this->em->flush();

        $this->audit->log(
            'cerfa_field_config.reset',
            'CerfaFieldConfig',
            $field->getId(),
            sprintf('%s.%s', $field->getCerfaRef(), $field->getFieldKey()),
        );

        return $this->json(
            $this->serializer->normalize($field, null, ['groups' => 'cerfa_config:read'])
        );
    }

    /** Génère un aperçu PDF avec les positions actuelles (sans données réelles — debug visuel) */
    #[Route('/preview/{cerfaRef}', methods: ['GET'])]
    public function preview(string $cerfaRef): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!isset(CerfaFieldConfig::CERFA_REFS[$cerfaRef])) {
            return $this->json(['error' => 'CERFA ref invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Retourne les champs de config + leurs positions pour affichage côté front
        $fields = $this->em->getRepository(CerfaFieldConfig::class)->findBy(
            ['cerfaRef' => $cerfaRef, 'isActive' => true],
            ['fieldKey' => 'ASC']
        );

        $data = $this->serializer->normalize($fields, null, ['groups' => 'cerfa_config:read']);

        return $this->json(['cerfa_ref' => $cerfaRef, 'fields' => $data]);
    }

    private function ensureDefaultRows(?string $onlyRef = null): void
    {
        $defaultsByRef = CerfaFieldConfigDefaults::all();
        $refs = $onlyRef ? [$onlyRef] : array_keys($defaultsByRef);
        $created = 0;

        foreach ($refs as $ref) {
            if (!isset($defaultsByRef[$ref])) {
                continue;
            }

            $existingRows = $this->em->getRepository(CerfaFieldConfig::class)->findBy(['cerfaRef' => $ref]);
            $existingKeys = [];
            foreach ($existingRows as $row) {
                $existingKeys[$row->getFieldKey()] = true;
            }

            foreach ($defaultsByRef[$ref] as $fieldKey => $default) {
                if (isset($existingKeys[$fieldKey])) {
                    continue;
                }

                $config = new CerfaFieldConfig();
                $config
                    ->setCerfaRef($ref)
                    ->setFieldKey($fieldKey)
                    ->setLabel((string) $default['label'])
                    ->setX((string) $default['x'])
                    ->setY((string) $default['y'])
                    ->setWidth((string) $default['width'])
                    ->setFontSize((string) $default['font_size'])
                    ->setFieldType((string) $default['field_type'])
                    ->setDescription($default['description'] ?? null)
                    ->setCharBoxWidth((string) ($default['char_box_width'] ?? '0'))
                    ->setCharGap((string) ($default['char_gap'] ?? '0'))
                    ->setDateGroupGap((string) ($default['date_group_gap'] ?? '0'))
                    ->setIsActive(true);

                $this->em->persist($config);
                $created++;
            }
        }

        if ($created > 0) {
            $this->em->flush();
        }
    }
}
