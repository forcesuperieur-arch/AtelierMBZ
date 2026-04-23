<?php

namespace App\Infrastructure;

class InputNormalizer
{
    public function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    public function requiredString(mixed $value, string $message): string
    {
        $normalized = $this->nullableString($value);
        if ($normalized === null) {
            throw new \InvalidArgumentException($message);
        }

        return $normalized;
    }

    public function decimalString(mixed $value): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return '0.00';
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public function normalizeBool(mixed $value): bool
    {
        return !in_array($value, [false, 0, '0', 'false', 'off', null], true);
    }
}
