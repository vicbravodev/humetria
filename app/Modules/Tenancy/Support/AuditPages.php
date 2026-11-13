<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Throwable;

/**
 * The audit trail as the console reads it: newest first, fifty to a page, narrowed by family of event, company, local days and
 * the email of whoever acted. Read only, and it shows each entry's context exactly as stored: nothing is joined to it.
 */
final class AuditPages
{
    private const int PER_PAGE = 50;

    /**
     * The families of event the trail knows, as the part of the event before its dot; a fixed catalog so the filter never
     * has to scan the table for its options.
     *
     * @var array<string, string>
     */
    public const array FAMILIES = [
        'tenant' => 'Empresas',
        'tenant_invitation' => 'Invitaciones',
        'tenant_member' => 'Miembros',
        'user' => 'Usuarios de la plataforma',
        'impersonation' => 'Impersonación',
        'billing' => 'Cobranza',
        'demo' => 'Solicitudes de demo',
        'campaign' => 'Campañas',
        'results' => 'Resultados',
        'complaint' => 'Quejas',
        'complaint_channel' => 'Canal de quejas',
        'complaint_handler' => 'Responsables de quejas',
        'interventions' => 'Intervenciones',
        'nom035' => 'NOM-035',
        'disclosure' => 'Difusión',
        'engagement' => 'Clima',
        'seating' => 'Ley Silla',
        'telework' => 'Teletrabajo',
        'attendance' => 'Asistencia',
        'leave' => 'Vacaciones',
        'compensation' => 'Compensación',
        'employee' => 'Personas',
        'assistant' => 'Humi',
    ];

    /**
     * The Spanish name of the states, plans, kinds and tools the detail stores by their code; one not here is shown as stored.
     *
     * @var array<string, string>
     */
    public const array VALUES = [
        'not_started' => 'Sin iniciar',
        'trial' => 'En prueba',
        'active' => 'Activa',
        'trial_ended' => 'Prueba terminada',
        'expired' => 'Vencida',
        'read_only' => 'Solo lectura',
        'suspended' => 'Suspendida',
        'archived' => 'Archivada',
        'past_due' => 'Con saldo vencido',
        'cancel_scheduled' => 'Cancelación programada',
        'ended' => 'Terminada',
        'jornada' => 'Jornada',
        'cumplimiento' => 'Cumplimiento',
        'integral' => 'Integral',
        'direct' => 'Directo',
        'consultant' => 'Consultor',
        'channel_compliance' => 'Canal Cumplimiento',
        'channel_integral' => 'Canal Integral',
        'negotiated' => 'Precio negociado',
        'recurring' => 'Recurrente',
        'one_time' => 'Una sola vez',
        'draft' => 'Borrador',
        'issued' => 'Emitido',
        'owner' => 'Dueño',
        'hr' => 'RH',
        'viewer' => 'Consulta',
        'committee' => 'Comisión',
        'complaints' => 'Quejas',
        'attendance' => 'Asistencia',
        'vacations' => 'Vacaciones',
        'org_chart' => 'Organigrama',
        'nom035' => 'NOM-035',
        'nom037' => 'NOM-037',
        'seating' => 'Ley Silla',
        'pulse' => 'Pulso',
        'climate' => 'Clima',
    ];

    /**
     * The Spanish name of the keys the detail shows most; one not here is shown as its own name with spaces.
     *
     * @var array<string, string>
     */
    public const array KEYS = [
        'user_id' => 'Persona',
        'reason' => 'Motivo',
        'previous' => 'Estado anterior',
        'role' => 'Rol',
        'module' => 'Herramienta',
        'until' => 'Hasta',
        'plan' => 'Plan',
        'status' => 'Estado',
        'seats' => 'Lugares',
        'centers' => 'Centros',
        'period' => 'Periodo',
        'kind' => 'Tipo',
        'changed' => 'Campos cambiados',
        'fields' => 'Campos cambiados',
        'enabled' => 'Herramientas activadas',
        'disabled' => 'Herramientas quitadas',
        'amount_cents' => 'Monto',
        'work_center_id' => 'Centro de trabajo',
        'work_center_ids' => 'Centros de trabajo',
        'work_center_count' => 'Centros de trabajo',
        'digest' => 'Huella del documento',
        'corrects_mark_id' => 'Marca que corrige',
        'employee_id' => 'Persona',
        'employee_ids' => 'Personas',
        'delivered_by_rh' => 'Entregado por RH',
        'sent_to' => 'Enviado a',
        'mark_id' => 'Marca',
        'sent' => 'Enviados',
        'without_email' => 'Sin correo',
        'linked' => 'Con enlace',
        'with_link' => 'Con enlace',
        'center_ready' => 'Centro listo',
        'from_manager_id' => 'Jefe anterior',
        'to_manager_id' => 'Jefe nuevo',
        'terminated_on' => 'Fecha de baja',
        'version' => 'Versión',
        'versions' => 'Versiones',
        'record_id' => 'Registro',
        'medium' => 'Medio',
        'campaign_id' => 'Campaña',
        'expires_on' => 'Vence el',
        'document_id' => 'Documento',
        'document_ids' => 'Documentos',
        'count' => 'Cantidad',
        'covers' => 'Cubre',
        'show_measures' => 'Mostrar medidas',
        'area' => 'Área',
        'area_id' => 'Área',
        'monthly_token_budget' => 'Presupuesto mensual de uso',
        'resolution' => 'Resolución',
        'from' => 'Desde',
        'to' => 'Hasta',
        'written' => 'Redactado',
        'equipment_id' => 'Equipo',
        'teleworker_id' => 'Persona en teletrabajo',
        'ended_by' => 'Terminó',
        'training_id' => 'Capacitación',
        'topic_count' => 'Temas',
        'attendee_count' => 'Asistentes',
        'notice_id' => 'Aviso',
        'checklist_id' => 'Lista de verificación',
        'missing_section_1' => 'Falta en la sección 1',
        'missing_section_2' => 'Falta en la sección 2',
        'registered_by' => 'Registró',
        'workplaces' => 'Lugares de trabajo',
        'period_months' => 'Periodo (meses)',
        'previous_period_months' => 'Periodo anterior (meses)',
        'consent_version' => 'Versión del consentimiento',
        'member_ids' => 'Integrantes',
        'review_id' => 'Revisión',
        'workplace_id' => 'Lugar de trabajo',
        'application_id' => 'Solicitud',
        'decision' => 'Decisión',
        'method' => 'Método',
        'joined_by' => 'Incorporó',
        'has_teleworkers' => 'Con personas en teletrabajo',
        'recorded_by' => 'Registró',
        'ready_only' => 'Solo los listos',
        'previous_user_id' => 'Usuario anterior',
        'member_user_id' => 'Integrante',
        'active_until' => 'Activa hasta',
        'cut' => 'Corte',
        'view' => 'Vista',
        'waves' => 'Olas',
        'item' => 'Elemento',
        'manager_id' => 'Jefe directo',
        'automatic' => 'Automático',
        'survey_id' => 'Encuesta',
        'group' => 'Grupo',
        'created' => 'Alta nueva',
        'commitments' => 'Compromisos',
        'published_by' => 'Publicó',
        'invited' => 'Invitados',
        'frequency' => 'Frecuencia',
        'day_of_month' => 'Día del mes',
        'open_days' => 'Días abierta',
        'active' => 'Activo',
        'next_mode' => 'Modo siguiente',
        'share_id' => 'Enlace',
        'minutes' => 'Minutos',
        'scenario_id' => 'Escenario',
        'days' => 'Días',
        'rows' => 'Filas',
        'grades' => 'Categorías',
        'overrides' => 'Sueldos excepcionales',
        'grade_salaries' => 'Sueldos de nivel',
        'people' => 'Personas',
        'instruments' => 'Instrumentos',
        'population' => 'Población',
        'sample_size' => 'Tamaño de muestra',
        'workers' => 'Trabajadores',
        'participants' => 'Participantes',
        'completed' => 'Completados',
        'pending' => 'Cambio programado',
        'acknowledged_shortfall' => 'Faltantes reconocidos',
        'answered' => 'Contestaron',
        'target' => 'Meta',
        'assessment_id' => 'Evaluación',
        'level' => 'Nivel',
        'seat_id' => 'Asiento',
        'use' => 'Uso',
        'position_id' => 'Puesto',
        'placement' => 'Ubicación',
        'referral_id' => 'Canalización',
        'regulation_id' => 'Reglamento',
        'report_id' => 'Reporte',
        'sector' => 'Sector',
        'no_standing_positions' => 'Sin puestos de pie',
        'action_id' => 'Acción',
        'program_id' => 'Programa',
        'entry_id' => 'Movimiento',
        'responsible_is_member' => 'El responsable es integrante',
        'by_system' => 'Por el sistema',
        'charge_on' => 'Fecha del cargo',
        'billing_account_id' => 'Cuenta de cobro',
        'allocated_cents' => 'Monto aplicado',
        'statements' => 'Estados de cuenta',
        'adjusts_statement_id' => 'Estado de cuenta ajustado',
        'total_cents' => 'Total',
        'report' => 'Reporte',
        'released_charges' => 'Cargos liberados',
        'profile' => 'Perfil de precio',
        'effective_on' => 'Vigente desde',
        'catalog_version' => 'Versión del catálogo',
        'negotiated_price_cents' => 'Precio negociado',
        'meter' => 'Medidor',
        'quantity' => 'Cantidad',
        'period_start' => 'Inicio del periodo',
        'account_id' => 'Cuenta de cobro',
        'starts_on' => 'Empieza el',
        'ends_on' => 'Termina el',
        'profile_before' => 'Perfil antes',
        'profile_after' => 'Perfil después',
        'approved' => 'Aprobada',
        'working_days' => 'Días hábiles',
        'authorized_by_employee_id' => 'Autorizó (persona)',
        'authorized_by_user_id' => 'Autorizó (usuario)',
        'request_id' => 'Solicitud',
        'by_hr' => 'Por RH',
        'premium_percent' => 'Prima (%)',
        'was' => 'Era',
        'days_returned' => 'Días devueltos',
        'periods' => 'Periodos',
        'tranche' => 'Tramo',
        'scope' => 'Alcance',
        'policy_id' => 'Política',
        'staff_group' => 'Grupo de personal',
        'job_grade_id' => 'Categoría y nivel',
    ];

    /**
     * @return array{q: string, family: string, company: string, from: string, to: string}
     */
    public function filters(string $q, string $family, string $company, string $from, string $to): array
    {
        return [
            'q' => trim($q),
            'family' => array_key_exists($family, self::FAMILIES) ? $family : '',
            'company' => Str::isUuid($company) ? $company : '',
            'from' => $this->day($from)?->toDateString() ?? '',
            'to' => $this->day($to)?->toDateString() ?? '',
        ];
    }

    /**
     * @param  array{q: string, family: string, company: string, from: string, to: string}  $filters
     * @return LengthAwarePaginator<int, array{id: string, at: string, event: string, actor: array{name: string, email: string}|null, impersonator: array{name: string, email: string}|null, company: array{id: string, name: string}|null, subject: string|null, context: array<string, string>}>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $page = $this->matching($filters)->latest('created_at')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        $entries = $page->getCollection();
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

        return $page->through(fn (AuditEntry $entry): array => [
            'id' => $entry->id,
            // Carbon's Spanish months end in a dot ("nov."); the design system writes "12 nov 2026".
            'at' => str_replace('.', '', $entry->created_at->setTimezone($timezone)->isoFormat('D MMM YYYY, HH:mm')),
            'event' => $entry->event,
            'actor' => $entry->actor_id === null ? null : ($people[$entry->actor_id] ?? null),
            'impersonator' => $entry->impersonator_id === null ? null : ($people[$entry->impersonator_id] ?? null),
            'company' => ($id = $this->companyId($entry)) === null || ! isset($companies[$id])
                ? null
                : ['id' => $id, 'name' => $companies[$id]],
            'subject' => $entry->subject_type,
            'context' => $this->context($entry, $companies, $people),
        ]);
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

    /** The name of a company, for the chip that says the trail is narrowed to it. */
    public function companyName(string $company): ?string
    {
        return $company === '' ? null : Tenant::query()->find($company, ['id', 'trade_name'])?->trade_name;
    }

    /**
     * @param  array{q: string, family: string, company: string, from: string, to: string}  $filters
     * @return Builder<AuditEntry>
     */
    private function matching(array $filters): Builder
    {
        $timezone = config()->string('app.display_timezone');

        return AuditEntry::query()
            ->when($filters['family'] !== '', fn (Builder $query) => $query->where('event', 'like', $filters['family'].'.%'))
            ->when($filters['company'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('tenant_id', $filters['company'])
                // Entries saved before the company was stored on them: it is in their metadata or is their subject.
                ->orWhere(fn (Builder $query) => $query->whereNull('tenant_id')->where(fn (Builder $query) => $query
                    ->whereRaw("metadata->>'tenant_id' = ?", [$filters['company']])
                    ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Tenant)->getMorphClass())->where('subject_id', $filters['company']))))))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->whereIn('actor_id', User::query()
                ->select('id')
                ->where('email', 'ilike', '%'.addcslashes($filters['q'], '\\%_').'%')));
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
     * @return array<string, string>
     */
    private function context(AuditEntry $entry, array $companies, array $people): array
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
            if ($key === 'tenant_id') {
                continue;
            }

            $rows[self::KEYS[$key] ?? ucfirst(str_replace('_', ' ', $key))] = $key === 'user_id' && is_int($value) && isset($people[$value])
                ? $this->person($people[$value])
                : $this->value($key, $value);
        }

        return $rows;
    }

    /** A stored value as a person reads it: yes or no, a state or a module by its name, a date in local time, cents as pesos. */
    private function value(string $key, mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Sí' : 'No',
            is_array($value) && array_is_list($value) => implode(', ', array_map(fn (mixed $item): string => $this->value($key, $item), $value)),
            is_array($value) => implode(', ', array_map(fn (mixed $item, int|string $name): string => (self::KEYS[$name] ?? ucfirst(str_replace('_', ' ', (string) $name))).': '.$this->value((string) $name, $item), $value, array_keys($value))),
            ! is_scalar($value) => '',
            str_ends_with($key, '_cents') && is_numeric($value) => '$'.number_format((int) $value / 100, 2),
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(T[\d:.]+(Z|[+-]\d{2}:\d{2}))?$/', $value) === 1 => $this->date($value),
            is_string($value) => self::VALUES[$value] ?? $value,
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
