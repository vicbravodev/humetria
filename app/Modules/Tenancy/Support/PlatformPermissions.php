<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What each platform role may do, asked of the gates themselves: a gate added or changed shows here as it is, so the table
 * never says what the code does not. Only the Spanish name of each permission is written by hand.
 */
final class PlatformPermissions
{
    /** @var array<string, string> */
    public const array LABELS = [
        'view-console' => 'Ver la consola y entrar a una empresa como soporte',
        'manage-tenants' => 'Crear, activar, suspender y archivar empresas',
        'view-platform-overview' => 'Ver el resumen de la plataforma',
        'view-billing' => 'Ver cuentas y cobranza',
        'manage-billing' => 'Cambiar planes y precios, y registrar cargos y pagos',
        'view-demo-requests' => 'Ver las solicitudes de demo',
        'manage-demo-requests' => 'Atender y convertir solicitudes de demo',
        'manage-demo' => 'Manejar la sala de demo',
        'manage-platform-users' => 'Ayudar a personas con cuenta y dar o quitar el rol de soporte',
        'view-audit' => 'Leer la bitácora',
        'view-operations' => 'Ver la salud del sistema y la mensajería',
        'viewHorizon' => 'Ver las colas',
    ];

    /**
     * @return list<array{ability: string, label: string, support: bool, superadmin: bool}>
     */
    public function handle(): array
    {
        $support = Gate::forUser(User::holdingPlatformRole(User::SUPPORT));
        $superadmin = Gate::forUser(User::holdingPlatformRole(User::SUPERADMIN));
        $rows = [];

        foreach (array_keys(Gate::abilities()) as $ability) {
            $rows[] = [
                'ability' => $ability,
                'label' => self::LABELS[$ability] ?? $ability,
                'support' => $support->allows($ability),
                'superadmin' => $superadmin->allows($ability),
            ];
        }

        return $rows;
    }
}
