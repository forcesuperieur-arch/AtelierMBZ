<?php
namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    /** Sentinel posé par BookingAtelierAccessService::withCrossAtelierRead() à la fermeture de la
     *  fenêtre cross-atelier : signifie "pas de liste active", retombe sur le scope atelier_id normal. */
    private const NO_LIST_OVERRIDE = '__NONE__';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('atelierId') && !$targetEntity->hasAssociation('atelierId')) {
            $columns = array_map(fn($m) => $m['columnName'] ?? $m['fieldName'], $targetEntity->fieldMappings);
            if (!in_array('atelier_id', $columns, true) && !in_array('atelierId', $columns, true)) {
                return '';
            }
        }

        // Liste d'ateliers (lecture cross-atelier SRC, posée temporairement par
        // BookingAtelierAccessService::withCrossAtelierRead) : prioritaire sur le scope scalaire.
        try {
            $rawList = trim((string) $this->getParameter('atelier_ids'), "'");
        } catch (\InvalidArgumentException) {
            $rawList = null;
        }
        if ($rawList !== null && $rawList !== self::NO_LIST_OVERRIDE) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $rawList)), fn($v) => $v > 0));
            if ($ids === []) {
                // Liste explicitement vide : aucun atelier autorisé, deny-all (fail-safe).
                return sprintf('%s.atelier_id = 0', $targetTableAlias);
            }

            return sprintf('%s.atelier_id IN (%s)', $targetTableAlias, implode(',', $ids));
        }

        try {
            $atelierId = $this->getParameter('atelier_id');
        } catch (\InvalidArgumentException) {
            return '';
        }

        return sprintf('%s.atelier_id = %s', $targetTableAlias, $atelierId);
    }
}
