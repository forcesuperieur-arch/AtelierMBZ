<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class VONumberingService
{
    public function __construct(private Connection $connection)
    {
    }

    public function nextFactureNumber(?int $atelierId, ?\DateTimeInterface $date = null): string
    {
        $resolvedDate = $date ?? new \DateTimeImmutable();
        $year = (int) $resolvedDate->format('Y');
        $sequence = $this->nextCounter('facture', $atelierId, $year);

        return sprintf('VOF-%d-%04d', $year, $sequence);
    }

    public function nextLivrePoliceOrder(?int $atelierId): int
    {
        return $this->nextCounter('livre_police', $atelierId, 0);
    }

    private function nextCounter(string $type, ?int $atelierId, int $year): int
    {
        return (int) $this->connection->fetchOne(
            <<<'SQL'
INSERT INTO vo_counters (counter_type, atelier_id, counter_year, counter_value)
VALUES (:type, :atelierId, :year, 1)
ON CONFLICT (counter_type, atelier_id, counter_year)
DO UPDATE SET
    counter_value = vo_counters.counter_value + 1,
    updated_at = CURRENT_TIMESTAMP
RETURNING counter_value
SQL,
            [
                'type' => $type,
                'atelierId' => $this->normalizeAtelierId($atelierId),
                'year' => $year,
            ],
        );
    }

    private function normalizeAtelierId(?int $atelierId): int
    {
        return $atelierId ?? 0;
    }
}