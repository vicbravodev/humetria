<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;

/**
 * «Qué puede hacer cada quien» (board V3-Roles), as the roles really are today: Spatie's permissions, and the routes a
 * restricted role may open (LimitRestrictedRoles). A cell that is not «No» is a role that has the permission of its row;
 * InviteMemberPageTest checks it against the permissions table. HR keeps channeling, the channel and compensation, and
 * nothing is per center yet (decision A6: only the labels change).
 */
final class RoleMatrix
{
    /** @var list<TenantRole> */
    public const array COLUMNS = [TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints];

    /** @var list<array{label: string, cells: list<string>}> */
    public const array ROWS = [
        ['label' => 'Ver rutas y expedientes', 'cells' => ['Sí', 'Sí', 'Rutas y expediente de NOM-037', 'NOM-037 y Ley Silla de sus centros', 'No']],
        ['label' => 'Avanzar tareas y documentos', 'cells' => ['Sí', 'Sí', 'No', 'Validar lugares y firmar evaluaciones', 'No']],
        ['label' => 'Resultados NOM-035 agregados', 'cells' => ['Sí', 'Sí', 'Sí', 'No', 'No']],
        ['label' => 'Canalización y datos de salud', 'cells' => ['Sí', 'Sí', 'No', 'No', 'No']],
        ['label' => 'Avisos de accidentes', 'cells' => ['Sí', 'Sí', 'No', 'De sus centros', 'No']],
        ['label' => 'Contenido de quejas y denuncias', 'cells' => ['Solo si se le designa', 'Solo si se le designa', 'No', 'Solo si se le designa', 'Solo si se le designa']],
        ['label' => 'Canal de quejas: quién atiende y conteos', 'cells' => ['Sí', 'Sí', 'No', 'No', 'No']],
        ['label' => 'Compensación: salarios y escenarios', 'cells' => ['Sí', 'Sí', 'No', 'No', 'No']],
        ['label' => 'Usuarios y configuración', 'cells' => ['Sí', 'No', 'No', 'No', 'No']],
    ];

    /**
     * The matrix a company shows; a union has only its owner and HR on its contract calculator, so it shows none.
     *
     * @return array{columns: list<string>, rows: list<array{label: string, cells: list<string>}>}|null
     */
    public static function for(TenantType $type): ?array
    {
        if ($type === TenantType::Union) {
            return null;
        }

        return ['columns' => array_map(fn (TenantRole $role): string => $role->label(), self::COLUMNS), 'rows' => self::ROWS];
    }
}
