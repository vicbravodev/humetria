<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Every permission the panel knows, with its label and conditions. PermissionCatalogTest ties it to the `permissions`
 * table: a migration that adds a permission without an entry here breaks CI.
 */
final class PermissionCatalog
{
    /** @var list<PermissionDefinition>|null */
    private static ?array $all = null;

    /** @return list<PermissionDefinition> */
    public static function all(): array
    {
        return self::$all ??= [
            new PermissionDefinition('view-dashboard', 'Ver el tablero', 'general', 'Abrir el tablero con el resumen de la empresa.'),
            new PermissionDefinition('view-work-centers', 'Ver centros de trabajo', 'general', 'Consultar los centros de trabajo y su situación.'),
            new PermissionDefinition('manage-organization', 'Administrar la organización', 'general', 'Crear y editar áreas, empleados, niveles y centros de trabajo.'),
            new PermissionDefinition('manage-members', 'Administrar miembros', 'general', 'Invitar personas y cambiar sus roles.', ownerOnly: true),
            new PermissionDefinition('use-assistant', 'Usar a Humi', 'general', 'Conversar con el asistente de IA.'),
            new PermissionDefinition('view-worker-access', 'Ver acceso de trabajadores', 'general', 'Consultar quién tiene acceso a su portal.'),
            new PermissionDefinition('manage-worker-access', 'Administrar acceso de trabajadores', 'general', 'Invitar y restablecer el acceso al portal de las personas trabajadoras.'),

            new PermissionDefinition('view-nom035-route', 'Ver la ruta NOM-035', 'nom035', 'Consultar si aplica, la ruta y el expediente de NOM-035.', requiresModule: Module::Nom035),
            new PermissionDefinition('manage-campaigns', 'Administrar campañas', 'nom035', 'Crear, lanzar y cerrar evaluaciones NOM-035.', requiresModule: Module::Nom035),
            new PermissionDefinition('view-trauma-screenings', 'Ver tamizajes de la Guía I', 'nom035', 'Ver la lista identificada de personas a canalizar.', sensitive: true, requiresModule: Module::Nom035),
            new PermissionDefinition('view-interventions', 'Ver intervenciones', 'nom035', 'Consultar el programa de intervención.', requiresModule: Module::Nom035),
            new PermissionDefinition('manage-interventions', 'Administrar intervenciones', 'nom035', 'Crear acciones, registrar avance y evidencia.', requiresModule: Module::Nom035),
            new PermissionDefinition('view-disclosure', 'Ver difusión', 'nom035', 'Consultar documentos, políticas y acuses.', requiresModule: Module::Nom035),
            new PermissionDefinition('manage-disclosure', 'Administrar difusión', 'nom035', 'Publicar documentos, políticas y resultados.', requiresModule: Module::Nom035),

            new PermissionDefinition('view-telework', 'Ver NOM-037', 'nom037', 'Consultar teletrabajadores y su lista de verificación.', requiresModule: Module::Nom037),
            new PermissionDefinition('manage-telework', 'Administrar NOM-037', 'nom037', 'Gestionar teletrabajadores, documentos y comité.', requiresModule: Module::Nom037),
            new PermissionDefinition('review-workplaces', 'Revisar espacios de trabajo', 'nom037', 'Revisar los espacios de trabajo de las personas teletrabajadoras.', sensitive: true, requiresModule: Module::Nom037),

            new PermissionDefinition('view-seating', 'Ver Ley Silla', 'seating', 'Consultar asientos, evaluaciones y medidas.', requiresModule: Module::Seating),
            new PermissionDefinition('manage-seating', 'Administrar Ley Silla', 'seating', 'Gestionar asientos, medidas y encuestas.', requiresModule: Module::Seating),
            new PermissionDefinition('assess-seating', 'Evaluar riesgo de Ley Silla', 'seating', 'Registrar la evaluación de riesgo de los puestos.', requiresModule: Module::Seating),

            new PermissionDefinition('manage-complaint-channel', 'Administrar el canal de quejas', 'complaints', 'Configurar el canal y designar a quien atiende.', sensitive: true, requiresModule: Module::Complaints),
            new PermissionDefinition('handle-complaints', 'Atender quejas', 'complaints', 'Ver y dar seguimiento a las quejas asignadas.', sensitive: true, requiresModule: Module::Complaints),

            new PermissionDefinition('view-attendance', 'Ver asistencia', 'attendance', 'Consultar marcas, horarios y correcciones.', requiresModule: Module::Attendance),
            new PermissionDefinition('manage-attendance', 'Administrar asistencia', 'attendance', 'Gestionar checadores, zonas, horarios y correcciones.', requiresModule: Module::Attendance),

            new PermissionDefinition('view-vacations', 'Ver vacaciones', 'vacations', 'Consultar saldos y solicitudes.', requiresModule: Module::Vacations),
            new PermissionDefinition('manage-vacations', 'Administrar vacaciones', 'vacations', 'Gestionar políticas, saldos y solicitudes.', requiresModule: Module::Vacations),

            new PermissionDefinition('view-org-chart', 'Ver organigrama', 'org_chart', 'Consultar el organigrama.', requiresModule: Module::OrgChart),
            new PermissionDefinition('manage-org-chart', 'Administrar organigrama', 'org_chart', 'Editar las líneas de reporte.', requiresModule: Module::OrgChart),

            new PermissionDefinition('view-pulse', 'Ver pulso', 'pulse', 'Consultar las encuestas de pulso y sus resultados.', requiresModule: Module::Pulse),
            new PermissionDefinition('manage-pulse', 'Administrar pulso', 'pulse', 'Crear y lanzar encuestas de pulso.', requiresModule: Module::Pulse),

            new PermissionDefinition('view-climate', 'Ver clima', 'climate', 'Consultar las encuestas de clima y sus resultados.', requiresModule: Module::Climate),
            new PermissionDefinition('manage-climate', 'Administrar clima', 'climate', 'Crear y lanzar encuestas de clima.', requiresModule: Module::Climate),

            new PermissionDefinition('view-compensation', 'Ver compensación', 'compensation', 'Consultar prestaciones, salarios y escenarios.', sensitive: true, requiresModule: Module::Compensation, unionAllowed: true),
            new PermissionDefinition('manage-compensation', 'Administrar compensación', 'compensation', 'Editar prestaciones, salarios y escenarios.', sensitive: true, requiresModule: Module::Compensation, unionAllowed: true),
        ];
    }

    public static function find(string $name): ?PermissionDefinition
    {
        foreach (self::all() as $permission) {
            if ($permission->name === $name) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * What the owner can switch on for a role of this account: not the owner's own, and for a union only compensation.
     *
     * @return list<PermissionDefinition>
     */
    public static function assignable(Tenant $tenant): array
    {
        return array_values(array_filter(
            self::all(),
            fn (PermissionDefinition $permission): bool => ! $permission->ownerOnly && $tenant->type->admits([$permission->name]),
        ));
    }

    /** Why the company cannot use the permission (its plan lacks the module), or null when it can. */
    public static function unavailableReason(Tenant $tenant, PermissionDefinition $permission): ?string
    {
        if ($permission->requiresModule === null || resolve(CompanyModules::class)->enabled($tenant->id, $permission->requiresModule->value)) {
            return null;
        }

        return 'Tu plan no incluye '.$permission->requiresModule->label().'.';
    }
}
