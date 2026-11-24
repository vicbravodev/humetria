<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * The areas of «Roles y permisos» as the server reads them (Humi tells a person their level in each): one access level per
 * area, each including the one before, plus a few extras. The screen draws them from resources/js/lib/permission-areas-data.ts;
 * PermissionAreasTest holds this list and that one to tests/Fixtures/permissions/areas.json, so the three never drift.
 */
final class PermissionAreas
{
    private const array NONE = ['label' => 'Sin acceso', 'permissions' => []];

    /**
     * By area key, in the screen's order; each extra by permission, as Humi says it.
     *
     * @return array<string, array{label: string, levels: array<string, array{label: string, permissions: list<string>}>, extras: array<string, string>}>
     */
    public static function all(): array
    {
        $nom035 = ['view-nom035-route', 'view-interventions', 'view-disclosure'];

        return [
            'dashboard' => ['label' => 'Panel', 'levels' => ['none' => self::NONE, 'view' => ['label' => 'Ver', 'permissions' => ['view-dashboard']]], 'extras' => []],
            'organization' => ['label' => 'Centros de trabajo y empleados', 'levels' => [
                'none' => self::NONE,
                'view' => ['label' => 'Ver', 'permissions' => ['view-work-centers']],
                'manage' => ['label' => 'Administrar', 'permissions' => ['view-work-centers', 'manage-organization']],
            ], 'extras' => []],
            'worker-access' => self::ladder('Acceso de trabajadores', 'worker-access'),
            'assistant' => ['label' => 'Humi, el asistente', 'levels' => ['none' => self::NONE, 'use' => ['label' => 'Usar', 'permissions' => ['use-assistant']]], 'extras' => []],
            'nom035' => ['label' => 'NOM-035', 'levels' => [
                'none' => self::NONE,
                'view' => ['label' => 'Ver', 'permissions' => $nom035],
                'manage' => ['label' => 'Administrar', 'permissions' => [...$nom035, 'manage-campaigns', 'manage-interventions', 'manage-disclosure']],
            ], 'extras' => ['view-trauma-screenings' => 've los tamizajes de la Guía I']],
            'telework' => self::ladder('NOM-037, teletrabajo', 'telework', ['review-workplaces' => 'revisa espacios de teletrabajo']),
            'seating' => self::ladder('Ley Silla', 'seating', ['assess-seating' => 'evalúa el riesgo de Ley Silla']),
            'complaints' => ['label' => 'Canal de quejas', 'levels' => [
                'none' => self::NONE,
                'handle' => ['label' => 'Atender', 'permissions' => ['handle-complaints']],
                'manage' => ['label' => 'Administrar', 'permissions' => ['handle-complaints', 'manage-complaint-channel']],
            ], 'extras' => []],
            'climate' => self::ladder('Clima laboral', 'climate'),
            'pulse' => self::ladder('Pulso', 'pulse'),
            'attendance' => self::ladder('Asistencia', 'attendance'),
            'vacations' => self::ladder('Vacaciones', 'vacations'),
            'org-chart' => self::ladder('Organigrama', 'org-chart'),
            'compensation' => self::ladder('Compensación', 'compensation'),
        ];
    }

    /**
     * The usual ladder: «Sin acceso», «Ver» (view-x), «Administrar» (view-x and manage-x).
     *
     * @param  array<string, string>  $extras
     * @return array{label: string, levels: array<string, array{label: string, permissions: list<string>}>, extras: array<string, string>}
     */
    private static function ladder(string $label, string $tool, array $extras = []): array
    {
        return ['label' => $label, 'levels' => [
            'none' => self::NONE,
            'view' => ['label' => 'Ver', 'permissions' => ["view-{$tool}"]],
            'manage' => ['label' => 'Administrar', 'permissions' => ["view-{$tool}", "manage-{$tool}"]],
        ], 'extras' => $extras];
    }
}
