<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Logging\RedactSensitiveData;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The audit trail as the console reads it: newest first, fifty to a page, narrowed by family of event, company, local days and
 * the email of whoever acted. Read only, and it shows each entry's context exactly as stored: nothing is joined to it.
 */
final class AuditPages
{
    private const int PER_PAGE = 50; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /**
     * The families of event the trail knows, as the part of the event before its dot; a fixed catalog so the filter never
     * has to scan the table for its options.
     *
     * @var array<string, string>
     */
    public const array FAMILIES = [
        'tenant' => 'Empresas', // @pest-mutate-ignore: RemoveArrayItem
        'tenant_invitation' => 'Invitaciones', // @pest-mutate-ignore: RemoveArrayItem
        'tenant_member' => 'Miembros', // @pest-mutate-ignore: RemoveArrayItem
        'tenant_role' => 'Roles propios', // @pest-mutate-ignore: RemoveArrayItem
        'user' => 'Usuarios de la plataforma', // @pest-mutate-ignore: RemoveArrayItem
        'impersonation' => 'Impersonación', // @pest-mutate-ignore: RemoveArrayItem
        'billing' => 'Cobranza', // @pest-mutate-ignore: RemoveArrayItem
        'demo' => 'Solicitudes de demo', // @pest-mutate-ignore: RemoveArrayItem
        'campaign' => 'Campañas', // @pest-mutate-ignore: RemoveArrayItem
        'results' => 'Resultados', // @pest-mutate-ignore: RemoveArrayItem
        'complaint' => 'Quejas', // @pest-mutate-ignore: RemoveArrayItem
        'complaint_channel' => 'Canal de quejas', // @pest-mutate-ignore: RemoveArrayItem
        'complaint_handler' => 'Responsables de quejas', // @pest-mutate-ignore: RemoveArrayItem
        'interventions' => 'Intervenciones', // @pest-mutate-ignore: RemoveArrayItem
        'nom035' => 'NOM-035', // @pest-mutate-ignore: RemoveArrayItem
        'disclosure' => 'Difusión', // @pest-mutate-ignore: RemoveArrayItem
        'engagement' => 'Clima', // @pest-mutate-ignore: RemoveArrayItem
        'seating' => 'Ley Silla', // @pest-mutate-ignore: RemoveArrayItem
        'telework' => 'Teletrabajo', // @pest-mutate-ignore: RemoveArrayItem
        'attendance' => 'Asistencia', // @pest-mutate-ignore: RemoveArrayItem
        'worker_access' => 'Acceso de personas', // @pest-mutate-ignore: RemoveArrayItem
        'leave' => 'Vacaciones', // @pest-mutate-ignore: RemoveArrayItem
        'compensation' => 'Compensación', // @pest-mutate-ignore: RemoveArrayItem
        'employee' => 'Personas', // @pest-mutate-ignore: RemoveArrayItem
        'assistant' => 'Humi', // @pest-mutate-ignore: RemoveArrayItem
        'sso' => 'Inicio de sesión único', // @pest-mutate-ignore: RemoveArrayItem
        'tenancy' => 'Canales de comunicación', // @pest-mutate-ignore: RemoveArrayItem
        'messaging' => 'WhatsApp', // @pest-mutate-ignore: RemoveArrayItem
        'audit' => 'Bitácora', // @pest-mutate-ignore: RemoveArrayItem
        'announcement' => 'Avisos de la plataforma', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /** The keys that store what an entry changed: the detail shows them as before and after, not as rows of their own. */
    private const array CHANGE_KEYS = ['before', 'after', 'changes', 'profile_before', 'profile_after'];

    /**
     * The views of the trail by what matters most, each the events it holds as patterns (`like`): entering a company as
     * support; what is charged and what a company may use; results and other sensitive records seen or exported; and who
     * may do what.
     *
     * @var array<string, list<string>>
     */
    public const array KINDS = [
        'support' => ['impersonation.%'],
        'billing' => ['billing.%', 'tenant.trial_started', 'tenant.module_%', 'tenant.modules_synced'],
        // Anchored at the dot or the underscore before the verb: «reviewed» is not «viewed».
        'results' => ['%.viewed', '%\\_viewed', '%.exported', '%\\_exported', '%.share\\_opened'],
        'roles' => ['tenant_role.%', 'tenant_member.role_changed', 'tenant_member.permissions_changed', 'user.support_%', 'user.superadmin_created'],
    ];

    /**
     * The Spanish name of the states, plans, kinds and tools the detail stores by their code; one not here is shown as stored.
     *
     * @var array<string, string>
     */
    public const array VALUES = [
        'not_started' => 'Sin iniciar', // @pest-mutate-ignore: RemoveArrayItem
        'day' => 'Diurna', // @pest-mutate-ignore: RemoveArrayItem
        'night' => 'Nocturna', // @pest-mutate-ignore: RemoveArrayItem
        'mixed' => 'Mixta', // @pest-mutate-ignore: RemoveArrayItem
        'onsite' => 'Presencial', // @pest-mutate-ignore: RemoveArrayItem
        'remote' => 'Remoto', // @pest-mutate-ignore: RemoveArrayItem
        'trial' => 'En prueba', // @pest-mutate-ignore: RemoveArrayItem
        'active' => 'Activa', // @pest-mutate-ignore: RemoveArrayItem
        'trial_ended' => 'Prueba terminada', // @pest-mutate-ignore: RemoveArrayItem
        'expired' => 'Vencida', // @pest-mutate-ignore: RemoveArrayItem
        'read_only' => 'Solo lectura', // @pest-mutate-ignore: RemoveArrayItem
        'suspended' => 'Suspendida', // @pest-mutate-ignore: RemoveArrayItem
        'archived' => 'Archivada', // @pest-mutate-ignore: RemoveArrayItem
        'past_due' => 'Con saldo vencido', // @pest-mutate-ignore: RemoveArrayItem
        'cancel_scheduled' => 'Cancelación programada', // @pest-mutate-ignore: RemoveArrayItem
        'ended' => 'Terminada', // @pest-mutate-ignore: RemoveArrayItem
        'jornada' => 'Jornada', // @pest-mutate-ignore: RemoveArrayItem
        'cumplimiento' => 'Cumplimiento', // @pest-mutate-ignore: RemoveArrayItem
        'integral' => 'Integral', // @pest-mutate-ignore: RemoveArrayItem
        'sindicato' => 'Sindicato', // @pest-mutate-ignore: RemoveArrayItem
        'union' => 'Cuota fija de sindicato', // @pest-mutate-ignore: RemoveArrayItem
        'direct' => 'Directo', // @pest-mutate-ignore: RemoveArrayItem
        'consultant' => 'Consultor', // @pest-mutate-ignore: RemoveArrayItem
        'channel_compliance' => 'Canal Cumplimiento', // @pest-mutate-ignore: RemoveArrayItem
        'channel_integral' => 'Canal Integral', // @pest-mutate-ignore: RemoveArrayItem
        'negotiated' => 'Precio negociado', // @pest-mutate-ignore: RemoveArrayItem
        'recurring' => 'Recurrente', // @pest-mutate-ignore: RemoveArrayItem
        'one_time' => 'Una sola vez', // @pest-mutate-ignore: RemoveArrayItem
        'draft' => 'Borrador', // @pest-mutate-ignore: RemoveArrayItem
        'issued' => 'Emitido', // @pest-mutate-ignore: RemoveArrayItem
        'owner' => 'Dueño', // @pest-mutate-ignore: RemoveArrayItem
        'hr' => 'RH', // @pest-mutate-ignore: RemoveArrayItem
        'viewer' => 'Consulta', // @pest-mutate-ignore: RemoveArrayItem
        'committee' => 'Comisión', // @pest-mutate-ignore: RemoveArrayItem
        'complaints' => 'Quejas', // @pest-mutate-ignore: RemoveArrayItem
        'attendance' => 'Asistencia', // @pest-mutate-ignore: RemoveArrayItem
        'worker_access' => 'Acceso de personas', // @pest-mutate-ignore: RemoveArrayItem
        'vacations' => 'Vacaciones', // @pest-mutate-ignore: RemoveArrayItem
        'org_chart' => 'Organigrama', // @pest-mutate-ignore: RemoveArrayItem
        'nom035' => 'NOM-035', // @pest-mutate-ignore: RemoveArrayItem
        'nom037' => 'NOM-037', // @pest-mutate-ignore: RemoveArrayItem
        'seating' => 'Ley Silla', // @pest-mutate-ignore: RemoveArrayItem
        'pulse' => 'Pulso', // @pest-mutate-ignore: RemoveArrayItem
        'climate' => 'Clima', // @pest-mutate-ignore: RemoveArrayItem
        'sso' => 'Inicio de sesión único', // @pest-mutate-ignore: RemoveArrayItem
        'whatsapp' => 'WhatsApp', // @pest-mutate-ignore: RemoveArrayItem
        'replaced' => 'Reemplazada', // @pest-mutate-ignore: RemoveArrayItem
        'qr_window' => 'Ventana del QR', // @pest-mutate-ignore: RemoveArrayItem
        'declared_interval' => 'Intervalo declarado', // @pest-mutate-ignore: RemoveArrayItem
        'passkey' => 'Llave de acceso', // @pest-mutate-ignore: RemoveArrayItem
        'device_key' => 'Llave del teléfono', // @pest-mutate-ignore: RemoveArrayItem
        'wide_interval' => 'Intervalo ancho', // @pest-mutate-ignore: RemoveArrayItem
        'old_window' => 'QR viejo', // @pest-mutate-ignore: RemoveArrayItem
        'no_position' => 'Sin posición', // @pest-mutate-ignore: RemoveArrayItem
        'outside_zone' => 'Fuera de zona', // @pest-mutate-ignore: RemoveArrayItem
        'zones_not_configured' => 'Centro sin zonas', // @pest-mutate-ignore: RemoveArrayItem
        'declared_adjusted' => 'Hora ajustada', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /**
     * The Spanish name of the keys the detail shows most; one not here is shown as its own name with spaces.
     *
     * @var array<string, string>
     */
    public const array KEYS = [
        'opt_outs' => 'Bajas retiradas', // @pest-mutate-ignore: RemoveArrayItem
        'channel' => 'Canal', // @pest-mutate-ignore: RemoveArrayItem
        'wording_version' => 'Versión del aviso aceptado', // @pest-mutate-ignore: RemoveArrayItem
        'user_id' => 'Persona', // @pest-mutate-ignore: RemoveArrayItem
        'reason' => 'Motivo', // @pest-mutate-ignore: RemoveArrayItem
        'previous' => 'Estado anterior', // @pest-mutate-ignore: RemoveArrayItem
        'role' => 'Rol', // @pest-mutate-ignore: RemoveArrayItem
        'permissions' => 'Permisos', // @pest-mutate-ignore: RemoveArrayItem
        'added' => 'Permisos agregados', // @pest-mutate-ignore: RemoveArrayItem
        'removed' => 'Permisos quitados', // @pest-mutate-ignore: RemoveArrayItem
        'sensitive' => 'Incluye permisos sensibles', // @pest-mutate-ignore: RemoveArrayItem
        'label' => 'Nombre del rol', // @pest-mutate-ignore: RemoveArrayItem
        'based_on' => 'Basado en', // @pest-mutate-ignore: RemoveArrayItem
        'holders' => 'Titulares', // @pest-mutate-ignore: RemoveArrayItem
        'module' => 'Herramienta', // @pest-mutate-ignore: RemoveArrayItem
        'until' => 'Hasta', // @pest-mutate-ignore: RemoveArrayItem
        'plan' => 'Plan', // @pest-mutate-ignore: RemoveArrayItem
        'status' => 'Estado', // @pest-mutate-ignore: RemoveArrayItem
        'seats' => 'Lugares', // @pest-mutate-ignore: RemoveArrayItem
        'centers' => 'Centros', // @pest-mutate-ignore: RemoveArrayItem
        'period' => 'Periodo', // @pest-mutate-ignore: RemoveArrayItem
        'kind' => 'Tipo', // @pest-mutate-ignore: RemoveArrayItem
        'changed' => 'Campos cambiados', // @pest-mutate-ignore: RemoveArrayItem
        'fields' => 'Campos cambiados', // @pest-mutate-ignore: RemoveArrayItem
        'enabled' => 'Herramientas activadas', // @pest-mutate-ignore: RemoveArrayItem
        'disabled' => 'Herramientas quitadas', // @pest-mutate-ignore: RemoveArrayItem
        'amount_cents' => 'Monto', // @pest-mutate-ignore: RemoveArrayItem
        'work_center_id' => 'Centro de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'work_center_ids' => 'Centros de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'work_center_count' => 'Centros de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'digest' => 'Huella del documento', // @pest-mutate-ignore: RemoveArrayItem
        'corrects_mark_id' => 'Marca que corrige', // @pest-mutate-ignore: RemoveArrayItem
        'employee_id' => 'Persona', // @pest-mutate-ignore: RemoveArrayItem
        'employee_ids' => 'Personas', // @pest-mutate-ignore: RemoveArrayItem
        'time_basis' => 'Base de la hora', // @pest-mutate-ignore: RemoveArrayItem
        'signer_type' => 'Firmante', // @pest-mutate-ignore: RemoveArrayItem
        'interval_minutes' => 'Minutos del intervalo', // @pest-mutate-ignore: RemoveArrayItem
        'review_flags' => 'Banderas de revisión', // @pest-mutate-ignore: RemoveArrayItem
        'delivered_by_rh' => 'Entregado por RH', // @pest-mutate-ignore: RemoveArrayItem
        'mark_id' => 'Marca', // @pest-mutate-ignore: RemoveArrayItem
        'sent' => 'Enviados', // @pest-mutate-ignore: RemoveArrayItem
        'without_email' => 'Sin correo', // @pest-mutate-ignore: RemoveArrayItem
        'with_link' => 'Con enlace', // @pest-mutate-ignore: RemoveArrayItem
        'keys_revoked' => 'Llaves revocadas', // @pest-mutate-ignore: RemoveArrayItem
        'device_keys_revoked' => 'Llaves del teléfono revocadas', // @pest-mutate-ignore: RemoveArrayItem
        'replaced_id' => 'Llave reemplazada', // @pest-mutate-ignore: RemoveArrayItem
        'with_key' => 'Con llave', // @pest-mutate-ignore: RemoveArrayItem
        'from_manager_id' => 'Jefe anterior', // @pest-mutate-ignore: RemoveArrayItem
        'to_manager_id' => 'Jefe nuevo', // @pest-mutate-ignore: RemoveArrayItem
        'terminated_on' => 'Fecha de baja', // @pest-mutate-ignore: RemoveArrayItem
        'version' => 'Versión', // @pest-mutate-ignore: RemoveArrayItem
        'versions' => 'Versiones', // @pest-mutate-ignore: RemoveArrayItem
        'record_id' => 'Registro', // @pest-mutate-ignore: RemoveArrayItem
        'medium' => 'Medio', // @pest-mutate-ignore: RemoveArrayItem
        'campaign_id' => 'Campaña', // @pest-mutate-ignore: RemoveArrayItem
        'expires_on' => 'Vence el', // @pest-mutate-ignore: RemoveArrayItem
        'document_id' => 'Documento', // @pest-mutate-ignore: RemoveArrayItem
        'document_ids' => 'Documentos', // @pest-mutate-ignore: RemoveArrayItem
        'count' => 'Cantidad', // @pest-mutate-ignore: RemoveArrayItem
        'covers' => 'Cubre', // @pest-mutate-ignore: RemoveArrayItem
        'show_measures' => 'Mostrar medidas', // @pest-mutate-ignore: RemoveArrayItem
        'area' => 'Área', // @pest-mutate-ignore: RemoveArrayItem
        'area_id' => 'Área', // @pest-mutate-ignore: RemoveArrayItem
        'monthly_token_budget' => 'Presupuesto mensual de uso', // @pest-mutate-ignore: RemoveArrayItem
        'resolution' => 'Resolución', // @pest-mutate-ignore: RemoveArrayItem
        'from' => 'Desde', // @pest-mutate-ignore: RemoveArrayItem
        'to' => 'Hasta', // @pest-mutate-ignore: RemoveArrayItem
        'written' => 'Redactado', // @pest-mutate-ignore: RemoveArrayItem
        'equipment_id' => 'Equipo', // @pest-mutate-ignore: RemoveArrayItem
        'teleworker_id' => 'Persona en teletrabajo', // @pest-mutate-ignore: RemoveArrayItem
        'ended_by' => 'Terminó', // @pest-mutate-ignore: RemoveArrayItem
        'training_id' => 'Capacitación', // @pest-mutate-ignore: RemoveArrayItem
        'topic_count' => 'Temas', // @pest-mutate-ignore: RemoveArrayItem
        'attendee_count' => 'Asistentes', // @pest-mutate-ignore: RemoveArrayItem
        'notice_id' => 'Aviso', // @pest-mutate-ignore: RemoveArrayItem
        'checklist_id' => 'Lista de verificación', // @pest-mutate-ignore: RemoveArrayItem
        'missing_section_1' => 'Falta en la sección 1', // @pest-mutate-ignore: RemoveArrayItem
        'missing_section_2' => 'Falta en la sección 2', // @pest-mutate-ignore: RemoveArrayItem
        'registered_by' => 'Registró', // @pest-mutate-ignore: RemoveArrayItem
        'workplaces' => 'Lugares de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'period_months' => 'Periodo (meses)', // @pest-mutate-ignore: RemoveArrayItem
        'previous_period_months' => 'Periodo anterior (meses)', // @pest-mutate-ignore: RemoveArrayItem
        'consent_version' => 'Versión del consentimiento', // @pest-mutate-ignore: RemoveArrayItem
        'member_ids' => 'Integrantes', // @pest-mutate-ignore: RemoveArrayItem
        'review_id' => 'Revisión', // @pest-mutate-ignore: RemoveArrayItem
        'workplace_id' => 'Lugar de trabajo', // @pest-mutate-ignore: RemoveArrayItem
        'application_id' => 'Solicitud', // @pest-mutate-ignore: RemoveArrayItem
        'decision' => 'Decisión', // @pest-mutate-ignore: RemoveArrayItem
        'corrected' => 'Corregida', // @pest-mutate-ignore: RemoveArrayItem
        'method' => 'Método', // @pest-mutate-ignore: RemoveArrayItem
        'joined_by' => 'Incorporó', // @pest-mutate-ignore: RemoveArrayItem
        'has_teleworkers' => 'Con personas en teletrabajo', // @pest-mutate-ignore: RemoveArrayItem
        'recorded_by' => 'Registró', // @pest-mutate-ignore: RemoveArrayItem
        'ready_only' => 'Solo los listos', // @pest-mutate-ignore: RemoveArrayItem
        'previous_user_id' => 'Usuario anterior', // @pest-mutate-ignore: RemoveArrayItem
        'member_user_id' => 'Integrante', // @pest-mutate-ignore: RemoveArrayItem
        'active_until' => 'Activa hasta', // @pest-mutate-ignore: RemoveArrayItem
        'cut' => 'Corte', // @pest-mutate-ignore: RemoveArrayItem
        'view' => 'Vista', // @pest-mutate-ignore: RemoveArrayItem
        'waves' => 'Olas', // @pest-mutate-ignore: RemoveArrayItem
        'item' => 'Elemento', // @pest-mutate-ignore: RemoveArrayItem
        'manager_id' => 'Jefe directo', // @pest-mutate-ignore: RemoveArrayItem
        'automatic' => 'Automático', // @pest-mutate-ignore: RemoveArrayItem
        'survey_id' => 'Encuesta', // @pest-mutate-ignore: RemoveArrayItem
        'group' => 'Grupo', // @pest-mutate-ignore: RemoveArrayItem
        'created' => 'Alta nueva', // @pest-mutate-ignore: RemoveArrayItem
        'commitments' => 'Compromisos', // @pest-mutate-ignore: RemoveArrayItem
        'published_by' => 'Publicó', // @pest-mutate-ignore: RemoveArrayItem
        'invited' => 'Invitados', // @pest-mutate-ignore: RemoveArrayItem
        'domain' => 'Dominio', // @pest-mutate-ignore: RemoveArrayItem
        'domains' => 'Dominios', // @pest-mutate-ignore: RemoveArrayItem
        'issuer' => 'Proveedor (issuer)', // @pest-mutate-ignore: RemoveArrayItem
        'client_id' => 'Client ID', // @pest-mutate-ignore: RemoveArrayItem
        'frequency' => 'Frecuencia', // @pest-mutate-ignore: RemoveArrayItem
        'day_of_month' => 'Día del mes', // @pest-mutate-ignore: RemoveArrayItem
        'open_days' => 'Días abierta', // @pest-mutate-ignore: RemoveArrayItem
        'active' => 'Activo', // @pest-mutate-ignore: RemoveArrayItem
        'next_mode' => 'Modo siguiente', // @pest-mutate-ignore: RemoveArrayItem
        'share_id' => 'Enlace', // @pest-mutate-ignore: RemoveArrayItem
        'minutes' => 'Minutos', // @pest-mutate-ignore: RemoveArrayItem
        'ends_at' => 'Termina', // @pest-mutate-ignore: RemoveArrayItem
        'owner_user_id' => 'Responsable', // @pest-mutate-ignore: RemoveArrayItem
        'scheduled_at' => 'Agendada para', // @pest-mutate-ignore: RemoveArrayItem
        'account_ids' => 'Cuentas', // @pest-mutate-ignore: RemoveArrayItem
        'scenario_id' => 'Escenario', // @pest-mutate-ignore: RemoveArrayItem
        'days' => 'Días', // @pest-mutate-ignore: RemoveArrayItem
        'rows' => 'Filas', // @pest-mutate-ignore: RemoveArrayItem
        'grades' => 'Categorías', // @pest-mutate-ignore: RemoveArrayItem
        'overrides' => 'Sueldos excepcionales', // @pest-mutate-ignore: RemoveArrayItem
        'grade_salaries' => 'Sueldos de nivel', // @pest-mutate-ignore: RemoveArrayItem
        'people' => 'Personas', // @pest-mutate-ignore: RemoveArrayItem
        'instruments' => 'Instrumentos', // @pest-mutate-ignore: RemoveArrayItem
        'population' => 'Población', // @pest-mutate-ignore: RemoveArrayItem
        'sample_size' => 'Tamaño de muestra', // @pest-mutate-ignore: RemoveArrayItem
        'workers' => 'Trabajadores', // @pest-mutate-ignore: RemoveArrayItem
        'participants' => 'Participantes', // @pest-mutate-ignore: RemoveArrayItem
        'completed' => 'Completados', // @pest-mutate-ignore: RemoveArrayItem
        'pending' => 'Cambio programado', // @pest-mutate-ignore: RemoveArrayItem
        'acknowledged_shortfall' => 'Faltantes reconocidos', // @pest-mutate-ignore: RemoveArrayItem
        'answered' => 'Contestaron', // @pest-mutate-ignore: RemoveArrayItem
        'target' => 'Meta', // @pest-mutate-ignore: RemoveArrayItem
        'assessment_id' => 'Evaluación', // @pest-mutate-ignore: RemoveArrayItem
        'level' => 'Nivel', // @pest-mutate-ignore: RemoveArrayItem
        'seat_id' => 'Asiento', // @pest-mutate-ignore: RemoveArrayItem
        'use' => 'Uso', // @pest-mutate-ignore: RemoveArrayItem
        'position_id' => 'Puesto', // @pest-mutate-ignore: RemoveArrayItem
        'placement' => 'Ubicación', // @pest-mutate-ignore: RemoveArrayItem
        'referral_id' => 'Canalización', // @pest-mutate-ignore: RemoveArrayItem
        'regulation_id' => 'Reglamento', // @pest-mutate-ignore: RemoveArrayItem
        'report_id' => 'Reporte', // @pest-mutate-ignore: RemoveArrayItem
        'sector' => 'Sector', // @pest-mutate-ignore: RemoveArrayItem
        'no_standing_positions' => 'Sin puestos de pie', // @pest-mutate-ignore: RemoveArrayItem
        'action_id' => 'Acción', // @pest-mutate-ignore: RemoveArrayItem
        'program_id' => 'Programa', // @pest-mutate-ignore: RemoveArrayItem
        'entry_id' => 'Movimiento', // @pest-mutate-ignore: RemoveArrayItem
        'responsible_is_member' => 'El responsable es integrante', // @pest-mutate-ignore: RemoveArrayItem
        'by_system' => 'Por el sistema', // @pest-mutate-ignore: RemoveArrayItem
        'charge_on' => 'Fecha del cargo', // @pest-mutate-ignore: RemoveArrayItem
        'billing_account_id' => 'Cuenta de cobro', // @pest-mutate-ignore: RemoveArrayItem
        'allocated_cents' => 'Monto aplicado', // @pest-mutate-ignore: RemoveArrayItem
        'statements' => 'Estados de cuenta', // @pest-mutate-ignore: RemoveArrayItem
        'adjusts_statement_id' => 'Estado de cuenta ajustado', // @pest-mutate-ignore: RemoveArrayItem
        'total_cents' => 'Total', // @pest-mutate-ignore: RemoveArrayItem
        'report' => 'Reporte', // @pest-mutate-ignore: RemoveArrayItem
        'released_charges' => 'Cargos liberados', // @pest-mutate-ignore: RemoveArrayItem
        'profile' => 'Perfil de precio', // @pest-mutate-ignore: RemoveArrayItem
        'effective_on' => 'Vigente desde', // @pest-mutate-ignore: RemoveArrayItem
        'catalog_version' => 'Versión del catálogo', // @pest-mutate-ignore: RemoveArrayItem
        'negotiated_price_cents' => 'Precio negociado', // @pest-mutate-ignore: RemoveArrayItem
        'meter' => 'Medidor', // @pest-mutate-ignore: RemoveArrayItem
        'quantity' => 'Cantidad', // @pest-mutate-ignore: RemoveArrayItem
        'period_start' => 'Inicio del periodo', // @pest-mutate-ignore: RemoveArrayItem
        'account_id' => 'Cuenta de cobro', // @pest-mutate-ignore: RemoveArrayItem
        'mode' => 'Modo', // @pest-mutate-ignore: RemoveArrayItem
        'recorded_on' => 'Fecha del avance', // @pest-mutate-ignore: RemoveArrayItem
        'with_evidence' => 'Con evidencia', // @pest-mutate-ignore: RemoveArrayItem
        'item_id' => 'Fila del programa', // @pest-mutate-ignore: RemoveArrayItem
        'changes' => 'Cambios', // @pest-mutate-ignore: RemoveArrayItem
        'copies' => 'Copias', // @pest-mutate-ignore: RemoveArrayItem
        'starts_on' => 'Empieza el', // @pest-mutate-ignore: RemoveArrayItem
        'ends_on' => 'Termina el', // @pest-mutate-ignore: RemoveArrayItem
        'profile_before' => 'Perfil antes', // @pest-mutate-ignore: RemoveArrayItem
        'profile_after' => 'Perfil después', // @pest-mutate-ignore: RemoveArrayItem
        'approved' => 'Aprobada', // @pest-mutate-ignore: RemoveArrayItem
        'working_days' => 'Días hábiles', // @pest-mutate-ignore: RemoveArrayItem
        'authorized_by_employee_id' => 'Autorizó (persona)', // @pest-mutate-ignore: RemoveArrayItem
        'authorized_by_user_id' => 'Autorizó (usuario)', // @pest-mutate-ignore: RemoveArrayItem
        'request_id' => 'Solicitud', // @pest-mutate-ignore: RemoveArrayItem
        'by_hr' => 'Por RH', // @pest-mutate-ignore: RemoveArrayItem
        'premium_percent' => 'Prima (%)', // @pest-mutate-ignore: RemoveArrayItem
        'was' => 'Era', // @pest-mutate-ignore: RemoveArrayItem
        'days_returned' => 'Días devueltos', // @pest-mutate-ignore: RemoveArrayItem
        'recorded_days' => 'Días registrados en la baja', // @pest-mutate-ignore: RemoveArrayItem
        'previous_terminated_on' => 'Fecha de la baja anterior', // @pest-mutate-ignore: RemoveArrayItem
        'previous_reason' => 'Motivo de la baja anterior', // @pest-mutate-ignore: RemoveArrayItem
        'periods' => 'Periodos', // @pest-mutate-ignore: RemoveArrayItem
        'tranche' => 'Tramo', // @pest-mutate-ignore: RemoveArrayItem
        'scope' => 'Alcance', // @pest-mutate-ignore: RemoveArrayItem
        'policy_id' => 'Política', // @pest-mutate-ignore: RemoveArrayItem
        'staff_group' => 'Grupo de personal', // @pest-mutate-ignore: RemoveArrayItem
        'job_grade_id' => 'Categoría y nivel', // @pest-mutate-ignore: RemoveArrayItem
        'valid_from' => 'Empieza el', // @pest-mutate-ignore: RemoveArrayItem
        'before' => 'Antes', // @pest-mutate-ignore: RemoveArrayItem
        'after' => 'Después', // @pest-mutate-ignore: RemoveArrayItem
        'shift_type' => 'Tipo de jornada', // @pest-mutate-ignore: RemoveArrayItem
        'work_mode' => 'Modalidad', // @pest-mutate-ignore: RemoveArrayItem
        'starts' => 'Hora de entrada', // @pest-mutate-ignore: RemoveArrayItem
        'job_title' => 'Puesto', // @pest-mutate-ignore: RemoveArrayItem
        'family' => 'Tipo de evento', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /**
     * @return array{q: string, kind: string, family: string, company: string, from: string, to: string}
     */
    public function filters(string $q, string $kind, string $family, string $company, string $from, string $to): array
    {
        return [
            'q' => trim($q),
            'kind' => array_key_exists($kind, self::KINDS) ? $kind : '',
            'family' => array_key_exists($family, self::FAMILIES) ? $family : '',
            'company' => Str::isUuid($company) ? $company : '',
            'from' => $this->day($from)?->toDateString() ?? '',
            'to' => $this->day($to)?->toDateString() ?? '',
        ];
    }

    /**
     * @param  array{q: string, kind: string, family: string, company: string, from: string, to: string}  $filters
     * @return LengthAwarePaginator<int, array{id: string, at: string, day: string, time: string, kind: string|null, origin: string, event: string, actor: array{name: string, email: string}|null, impersonator: array{name: string, email: string}|null, company: array{id: string, name: string}|null, subject: string|null, context: array<string, string>, change: list<array{field: string, before: string, after: string}>}>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $page = $this->matching($filters)->latest('created_at')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
        $rows = $this->present($page->getCollection());

        return $page->through(fn (AuditEntry $entry, int $key): array => $rows[$key]);
    }

    /**
     * The newest entries the filters find, up to a limit, as the console reads them: for the export.
     *
     * @param  array{q: string, kind: string, family: string, company: string, from: string, to: string}  $filters
     * @return list<array{id: string, at: string, day: string, time: string, kind: string|null, origin: string, event: string, actor: array{name: string, email: string}|null, impersonator: array{name: string, email: string}|null, company: array{id: string, name: string}|null, subject: string|null, context: array<string, string>, change: list<array{field: string, before: string, after: string}>}>
     */
    public function rows(array $filters, int $limit): array
    {
        return $this->present($this->matching($filters)->latest('created_at')->orderByDesc('id')->limit($limit)->get());
    }

    /**
     * Each entry as the console reads it, the people, companies and role labels of all of them read once.
     *
     * @param  \Illuminate\Support\Collection<int, AuditEntry>  $entries
     * @return list<array{id: string, at: string, day: string, time: string, kind: string|null, origin: string, event: string, actor: array{name: string, email: string}|null, impersonator: array{name: string, email: string}|null, company: array{id: string, name: string}|null, subject: string|null, context: array<string, string>, change: list<array{field: string, before: string, after: string}>}>
     */
    private function present(\Illuminate\Support\Collection $entries): array
    {
        $people = $this->people(
            $entries->pluck('actor_id')->merge($entries->pluck('impersonator_id'))
                ->merge($entries->map(fn (AuditEntry $entry): mixed => $this->namedUser($entry)))
                ->merge($entries->map(fn (AuditEntry $entry): mixed => $entry->metadata['user_id'] ?? null)),
        );
        $companies = [];

        $ids = $entries->map(fn (AuditEntry $entry): ?string => $this->companyId($entry))->filter()->unique()->values();
        $ids = $ids->merge($entries->map(fn (AuditEntry $entry): mixed => $entry->metadata['tenant_id'] ?? null)->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id)))->unique()->values();

        foreach (Tenant::query()->whereIn('id', $ids)->get(['id', 'trade_name']) as $tenant) {
            $companies[$tenant->id] = $tenant->trade_name;
        }
        $timezone = config()->string('app.display_timezone');
        $roles = $this->roleLabels($entries);
        $staff = $this->staff($entries->pluck('actor_id'));
        $today = CarbonImmutable::now($timezone);

        return array_values($entries->map(fn (AuditEntry $entry): array => [
            'id' => $entry->id,
            // Carbon's Spanish months end in a dot ("nov."); the design system writes "12 nov 2026".
            'at' => str_replace('.', '', $entry->created_at->setTimezone($timezone)->isoFormat('D MMM YYYY, HH:mm')),
            'day' => $this->dayLabel(CarbonImmutable::parse($entry->created_at)->setTimezone($timezone), $today),
            'time' => $entry->created_at->setTimezone($timezone)->format('H:i'),
            'kind' => $this->kind($entry->event),
            'origin' => match (true) {
                $entry->actor_id === null => 'Sistema',
                $entry->impersonator_id !== null => 'Como soporte',
                isset($staff[$entry->actor_id]) => 'Consola',
                default => 'Empresa',
            },
            'event' => $entry->event,
            'actor' => $entry->actor_id === null ? null : ($people[$entry->actor_id] ?? null),
            'impersonator' => $entry->impersonator_id === null ? null : ($people[$entry->impersonator_id] ?? null),
            'company' => ($id = $this->companyId($entry)) === null || ! isset($companies[$id])
                ? null
                : ['id' => $id, 'name' => $companies[$id]],
            'subject' => $entry->subject_type,
            'context' => $this->context($entry, $companies, $people, $roles),
            'change' => $this->change($entry, $roles),
        ])->all());
    }

    /** The day the table groups an entry under (tablero Bitácora): «Hoy · 25 nov», «Ayer · 24 nov», «Sábado · 22 nov», the year when it is not this one. */
    private function dayLabel(CarbonImmutable $at, CarbonImmutable $today): string
    {
        $date = str_replace('.', '', $at->isoFormat($at->year === $today->year ? 'D MMM' : 'D MMM YYYY'));
        $name = match (true) {
            $at->isSameDay($today) => 'Hoy',
            $at->isSameDay($today->subDay()) => 'Ayer',
            default => ucfirst($at->isoFormat('dddd')),
        };

        return "{$name} · {$date}";
    }

    /** The view of the trail an event belongs to, by the same patterns its tabs count with; null for none. */
    private function kind(string $event): ?string
    {
        foreach (self::KINDS as $kind => $patterns) {
            foreach ($patterns as $pattern) {
                $regex = preg_replace_callback('/\\\\_|%|_|[^%_\\\\]+/', fn (array $part): string => match ($part[0]) {
                    '\\_' => '_',
                    '%' => '.*',
                    '_' => '.',
                    default => preg_quote($part[0], '/'),
                }, $pattern);

                if (preg_match('/\A'.$regex.'\z/', $event) === 1) {
                    return $kind;
                }
            }
        }

        return null;
    }

    /**
     * Which of these people hold a platform role, in one query: what they do outside a company they do from the console.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     * @return array<int, true>
     */
    private function staff(\Illuminate\Support\Collection $ids): array
    {
        $staff = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [User::SUPERADMIN, User::SUPPORT])
            ->whereNull('model_has_roles.tenant_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', $ids->filter()->unique()->values())
            ->pluck('model_has_roles.model_id') as $id) {
            is_int($id) && $staff[$id] = true;
        }

        return $staff;
    }

    private function companyId(AuditEntry $entry): ?string
    {
        if ($entry->tenant_id !== null) {
            return $entry->tenant_id;
        }

        $named = $entry->metadata['tenant_id'] ?? null;

        if (is_string($named) && Str::isUuid($named)) {
            return $named;
        }

        return $entry->subject_type === (new Tenant)->getMorphClass() ? $entry->subject_id : null;
    }

    /**
     * How many entries each view holds under the rest of the filters, the whole trail first.
     *
     * @param  array{q: string, kind: string, family: string, company: string, from: string, to: string}  $filters
     * @return array<string, int>
     */
    public function counts(array $filters): array
    {
        $counts = ['all' => $this->matching([...$filters, 'kind' => ''])->count()];

        foreach (array_keys(self::KINDS) as $kind) {
            $counts[$kind] = $this->matching([...$filters, 'kind' => $kind])->count();
        }

        return $counts;
    }

    /**
     * Twenty companies at a time whose name holds what was typed, for the filter that narrows the trail to one.
     *
     * @return list<array{value: string, label: string}>
     */
    public function companies(string $search): array
    {
        return array_values(Tenant::query()
            ->matching(['trade_name'], $search)
            ->orderBy('trade_name')
            ->limit(20)
            ->get(['id', 'trade_name'])
            ->map(fn (Tenant $tenant): array => ['value' => $tenant->id, 'label' => $tenant->trade_name])
            ->all());
    }

    /** The name of a company, for the chip that says the trail is narrowed to it. */
    public function companyName(string $company): ?string
    {
        return $company === '' ? null : Tenant::query()->find($company, ['id', 'trade_name'])?->trade_name;
    }

    /**
     * @param  array{q: string, kind: string, family: string, company: string, from: string, to: string}  $filters
     * @return Builder<AuditEntry>
     */
    private function matching(array $filters): Builder
    {
        $timezone = config()->string('app.display_timezone');

        return AuditEntry::query()
            ->when($filters['kind'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                foreach (self::KINDS[$filters['kind']] as $pattern) {
                    $query->orWhere('event', 'like', $pattern);
                }
            }))
            ->when($filters['family'] !== '', fn (Builder $query) => $query->where('event', 'like', $filters['family'].'.%'))
            ->when($filters['company'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('tenant_id', $filters['company'])
                // Entries saved before the company was stored on them: it is in their metadata or is their subject.
                ->orWhere(fn (Builder $query) => $query->whereNull('tenant_id')->where(fn (Builder $query) => $query
                    ->whereRaw("metadata->>'tenant_id' = ?", [$filters['company']])
                    ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Tenant)->getMorphClass())->where('subject_id', $filters['company']))))))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc()))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()->utc()))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('actor_id', User::query()->select('id')->matching(['name', 'email'], $filters['q']))
                ->orWhereIn('tenant_id', Tenant::query()->select('id')->matching(['trade_name'], $filters['q']))));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     * @return array<int, array{name: string, email: string}>
     */
    private function people(\Illuminate\Support\Collection $ids): array
    {
        $people = [];

        foreach (User::query()->whereIn('id', $ids->filter()->unique()->values())->get(['id', 'name', 'email']) as $user) {
            $people[$user->id] = ['name' => $user->name, 'email' => $user->email];
        }

        return $people;
    }

    /** The user an entry is about when its subject is one (who was impersonated, whose password was reset). */
    private function namedUser(AuditEntry $entry): ?int
    {
        return $entry->subject_type === (new User)->getMorphClass() && ctype_digit((string) $entry->subject_id) ? (int) $entry->subject_id : null;
    }

    /**
     * What an entry stored as the console's detail: Spanish labels for the keys it knows, the company and the people by name
     * and not by id, and yes or no for a flag.
     *
     * @param  array<string, string>  $companies
     * @param  array<int, array{name: string, email: string}>  $people
     * @param  array<string, string>  $roles  the label of each role of a company the page names, by internal key
     * @return array<string, string>
     */
    private function context(AuditEntry $entry, array $companies, array $people, array $roles): array
    {
        $rows = [];
        $company = $entry->metadata['tenant_id'] ?? null;

        if (is_string($company) && isset($companies[$company])) {
            $rows['Empresa'] = $companies[$company];
        }

        $subject = $this->namedUser($entry);

        if ($subject !== null && isset($people[$subject]) && str_starts_with($entry->event, 'impersonation.')) {
            $rows['Persona suplantada'] = $this->person($people[$subject]);
        }

        foreach ($entry->metadata as $key => $value) {
            if ($key === 'tenant_id' || in_array($key, self::CHANGE_KEYS, true) || ! $this->shown($key)) {
                continue;
            }

            $rows[$this->label($key)] = $key === 'user_id' && is_int($value) && isset($people[$value])
                ? $this->person($people[$value])
                : $this->value($key, $value, $roles);
        }

        return $rows;
    }

    /**
     * What an entry changed, field by field, from what it stored as before and after: the two sides of a snapshot
     * (`before`, `after`), the fields it lists as changed (`changes`), or the two profiles of a transfer. Only what differs.
     *
     * @param  array<string, string>  $roles
     * @return list<array{field: string, before: string, after: string}>
     */
    private function change(AuditEntry $entry, array $roles): array
    {
        $metadata = $entry->metadata;
        $pairs = [];

        if (array_key_exists('before', $metadata) || array_key_exists('after', $metadata)) {
            $before = $metadata['before'] ?? null;
            $after = $metadata['after'] ?? null;

            if (is_array($before) || is_array($after)) {
                $before = is_array($before) ? $before : [];
                $after = is_array($after) ? $after : [];

                foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                    $pairs[(string) $key] = [$before[$key] ?? null, $after[$key] ?? null];
                }
            } else {
                $pairs['value'] = [$before, $after];
            }
        }

        foreach (is_array($metadata['changes'] ?? null) ? $metadata['changes'] : [] as $key => $pair) {
            if (is_array($pair)) {
                $pairs[(string) $key] = [$pair['before'] ?? null, $pair['after'] ?? null];
            }
        }

        if (array_key_exists('profile_before', $metadata) || array_key_exists('profile_after', $metadata)) {
            $pairs['profile'] = [$metadata['profile_before'] ?? null, $metadata['profile_after'] ?? null];
        }

        $rows = [];

        foreach ($pairs as $key => [$before, $after]) {
            if ($before !== $after && $this->shown($key)) {
                $rows[] = ['field' => $key === 'value' ? 'Valor' : $this->label($key), 'before' => $this->value($key, $before, $roles), 'after' => $this->value($key, $after, $roles)];
            }
        }

        return $rows;
    }

    /** Whether the detail may show a stored key: never one the logs keep out, unless the trail names it on purpose. */
    private function shown(int|string $key): bool
    {
        return isset(self::KEYS[$key]) || ! in_array(strtolower((string) $key), RedactSensitiveData::KEYS, true);
    }

    private function label(int|string $key): string
    {
        return self::KEYS[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    /**
     * The label of each of the company's own roles the page's entries name (`custom_<uuid>`), in one query.
     *
     * @param  \Illuminate\Support\Collection<int, AuditEntry>  $entries
     * @return array<string, string>
     */
    private function roleLabels(\Illuminate\Support\Collection $entries): array
    {
        $keys = [];
        $metadata = $entries->map(fn (AuditEntry $entry): array => $entry->metadata)->all();
        array_walk_recursive($metadata, function (mixed $value) use (&$keys): void {
            is_string($value) && str_starts_with($value, 'custom_') && $keys[$value] = true;
        });
        $labels = [];

        foreach ($keys === [] ? [] : DB::table('roles')->whereIn('name', array_keys($keys))->get(['name', 'label']) as $role) {
            is_string($role->name) && is_string($role->label) && $labels[$role->name] = $role->label;
        }

        return $labels;
    }

    /**
     * A stored value as a person reads it: yes or no, a state or a module by its name, a date in local time, cents as pesos,
     * a role of the company by its label.
     *
     * @param  array<string, string>  $roles
     */
    private function value(string $key, mixed $value, array $roles): string
    {
        return match (true) { // @pest-mutate-ignore: TrueToFalse
            $value === null => '—',
            is_bool($value) => $value ? 'Sí' : 'No',
            is_array($value) && array_is_list($value) => implode(', ', array_map(fn (mixed $item): string => $this->value($key, $item, $roles), $value)),
            is_array($value) => implode(', ', array_map(fn (mixed $item, int|string $name): string => (self::KEYS[$name] ?? ucfirst(str_replace('_', ' ', (string) $name))).': '.$this->value((string) $name, $item, $roles), $value, array_keys($value))),
            ! is_scalar($value) => '',
            str_ends_with($key, '_cents') && is_numeric($value) => '$'.number_format((int) $value / 100, 2),
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T[\d:.]+(Z|[+-]\d{2}:\d{2}))?$/', $value) === 1 => $this->date($value),
            is_string($value) => self::VALUES[$value] ?? $roles[$value] ?? $value,
            default => (string) $value,
        };
    }

    private function date(string $value): string
    {
        $date = str_contains($value, 'T')
            ? CarbonImmutable::parse($value)->setTimezone(config()->string('app.display_timezone'))
            : CarbonImmutable::parse($value);

        return str_replace('.', '', $date->isoFormat('D MMM YYYY'));
    }

    /** @param  array{name: string, email: string}  $person */
    private function person(array $person): string
    {
        return $person['name'].' ('.$person['email'].')';
    }

    private function day(string $value): ?CarbonImmutable
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $day instanceof CarbonImmutable && $day->toDateString() === $value ? $day : null;
    }
}
