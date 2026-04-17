<?php

namespace App\Service;

final class AbsenceConflictChecker
{
    public function isDateRangeValid(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        return $end >= $start;
    }

    public function hasConflict(\DateTimeInterface $start, \DateTimeInterface $end, array $existingRanges): bool
    {
        foreach ($existingRanges as $range) {
            $rangeStart = $range['start'] ?? null;
            $rangeEnd = $range['end'] ?? null;

            if (!$rangeStart instanceof \DateTimeInterface || !$rangeEnd instanceof \DateTimeInterface) {
                continue;
            }

            if ($rangeStart <= $end && $rangeEnd >= $start) {
                return true;
            }
        }

        return false;
    }
}
