<?php

namespace App\Service;

final class NotificationProviderConfigSanitizer
{
    public function merge(array $existing, array $submitted): array
    {
        $merged = $existing;

        foreach ($submitted as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
