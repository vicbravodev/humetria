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
        $people = $this->people($entries->pluck('actor_id')->merge($entries->pluck('impersonator_id')));
        $companies = [];

        foreach (Tenant::query()->whereIn('id', $entries->pluck('tenant_id')->filter()->unique()->values())->get(['id', 'trade_name']) as $tenant) {
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
            'company' => $entry->tenant_id === null || ! isset($companies[$entry->tenant_id])
                ? null
                : ['id' => $entry->tenant_id, 'name' => $companies[$entry->tenant_id]],
            'subject' => $entry->subject_type,
            'context' => $this->context($entry->metadata),
        ]);
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
            ->when($filters['company'] !== '', fn (Builder $query) => $query->where('tenant_id', $filters['company']))
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

    /**
     * What an entry stored, one line per key; a value that is not text is shown as JSON.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private function context(array $metadata): array
    {
        return array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: ''), $metadata);
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
