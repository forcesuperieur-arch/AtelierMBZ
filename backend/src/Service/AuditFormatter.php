<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Formats AuditLog entries into human-readable action labels and detail strings.
 */
class AuditFormatter
{
    public function formatAction(string $action, ?string $details): string
    {
        $payload = $this->decodeDetails($details);

        return match ($action) {
            'create' => 'Creation du rendez-vous',
            'workflow_transition' => sprintf(
                'Transition workflow%s',
                !empty($payload['transition']) ? ' : ' . (string) $payload['transition'] : ''
            ),
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }

    public function formatDetails(string $action, ?string $details): ?string
    {
        $payload = $this->decodeDetails($details);
        if ($payload === null) {
            return $details;
        }

        return match ($action) {
            'create' => sprintf(
                'Type: %s%s',
                (string) ($payload['type'] ?? '—'),
                !empty($payload['statut']) ? ' · Statut initial: ' . (string) $payload['statut'] : ''
            ),
            'workflow_transition' => sprintf(
                'Nouveau statut: %s',
                (string) ($payload['new_status'] ?? $payload['statut'] ?? '—')
            ),
            default => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeDetails(?string $details): ?array
    {
        if (!is_string($details) || trim($details) === '') {
            return null;
        }

        $decoded = json_decode($details, true);

        return is_array($decoded) ? $decoded : null;
    }
}
