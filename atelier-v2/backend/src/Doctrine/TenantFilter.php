<?php
namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL filter that automatically adds WHERE atelier_id = :id
 * to all queries on entities that have the atelier_id column.
 */
class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Check if the entity has an atelier_id field
        if (!$targetEntity->hasField('atelierId') && !$targetEntity->hasAssociation('atelierId')) {
            // Also check column name directly for mapped entities
            $columns = array_map(fn($m) => $m['columnName'] ?? $m['fieldName'], $targetEntity->fieldMappings);
            if (!in_array('atelier_id', $columns, true) && !in_array('atelierId', $columns, true)) {
                return '';
            }
        }

        try {
            $atelierId = $this->getParameter('atelier_id');
        } catch (\InvalidArgumentException) {
            return '';
        }

        return sprintf('%s.atelier_id = %s', $targetTableAlias, $atelierId);
    }
}
