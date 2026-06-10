<?php
namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasField('atelierId') && !$targetEntity->hasAssociation('atelierId')) {
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
