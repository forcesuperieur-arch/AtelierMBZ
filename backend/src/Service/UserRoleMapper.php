<?php

namespace App\Service;

use App\Entity\RoleMetier;

final class UserRoleMapper
{
    private const ROLE_METIER_TO_LEGACY = [
        'responsable_atelier' => 'admin',
        'responsable_magasin' => 'admin',
        'receptionniste' => 'receptionnaire',
        'mecanicien' => 'mecanicien',
        'comptable' => 'comptable',
        'vo_manager' => 'vo_manager',
        'service_client' => 'service_client',
    ];

    private const LEGACY_TO_ROLE_METIER = [
        'admin' => 'responsable_atelier',
        'receptionnaire' => 'receptionniste',
        'receptionniste' => 'receptionniste',
        'mecanicien' => 'mecanicien',
        'comptable' => 'comptable',
        'vo_manager' => 'vo_manager',
        'service_client' => 'service_client',
        'user' => 'service_client',
    ];

    public function mapRoleMetierToLegacyRole(RoleMetier $roleMetier): string
    {
        return self::ROLE_METIER_TO_LEGACY[$roleMetier->getCode()] ?? ($roleMetier->getBaseRole() === 'ROLE_ADMIN' ? 'admin' : 'user');
    }

    public function mapLegacyRoleToRoleMetierCode(?string $legacyRole): ?string
    {
        $normalized = strtolower(trim((string) $legacyRole));

        return self::LEGACY_TO_ROLE_METIER[$normalized] ?? null;
    }
}
