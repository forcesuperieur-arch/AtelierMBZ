<?php

namespace App\Service;

use App\Entity\ClauseLegale;

class ClauseLegaleVisibilityService
{
    /**
     * @param ClauseLegale[] $clauses
     * @return ClauseLegale[]
     */
    public function pickVisibleClauses(array $clauses, bool $activeOnly): array
    {
        if (!$activeOnly) {
            usort($clauses, [$this, 'compareClauses']);
            return $clauses;
        }

        $selectedByCode = [];

        foreach ($clauses as $clause) {
            $code = $clause->getCode();
            if (!isset($selectedByCode[$code]) || $this->isBetterCandidate($clause, $selectedByCode[$code])) {
                $selectedByCode[$code] = $clause;
            }
        }

        $visible = array_values(array_filter(
            $selectedByCode,
            static fn (ClauseLegale $clause): bool => $clause->isActive(),
        ));

        usort($visible, [$this, 'compareClauses']);

        return $visible;
    }

    /**
     * @param ClauseLegale[] $clauses
     */
    public function pickPreferredClause(array $clauses): ?ClauseLegale
    {
        $selected = null;

        foreach ($clauses as $clause) {
            if (!$selected || $this->isBetterCandidate($clause, $selected)) {
                $selected = $clause;
            }
        }

        return $selected;
    }

    private function isBetterCandidate(ClauseLegale $candidate, ClauseLegale $current): bool
    {
        $candidateScoped = $candidate->getAtelierId() !== null;
        $currentScoped = $current->getAtelierId() !== null;

        if ($candidateScoped !== $currentScoped) {
            return $candidateScoped;
        }

        if ($candidate->getVersion() !== $current->getVersion()) {
            return $candidate->getVersion() > $current->getVersion();
        }

        return $candidate->getCreatedAt() > $current->getCreatedAt();
    }

    private function compareClauses(ClauseLegale $a, ClauseLegale $b): int
    {
        $codeComparison = strcmp($a->getCode(), $b->getCode());
        if ($codeComparison !== 0) {
            return $codeComparison;
        }

        $scopeComparison = ($a->getAtelierId() === null ? 1 : 0) <=> ($b->getAtelierId() === null ? 1 : 0);
        if ($scopeComparison !== 0) {
            return $scopeComparison;
        }

        return $b->getVersion() <=> $a->getVersion();
    }
}
