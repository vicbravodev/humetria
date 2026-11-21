<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Actions\DisputeWeek;
use App\Modules\Attendance\Actions\ResolveDispute;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;

mutates(ResolveDispute::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City: the week of 2 November is over.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    foreach ([[MarkType::Start, '2026-11-03 09:00'], [MarkType::End, '2026-11-03 17:00']] as [$type, $local]) {
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id,
            'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
        ]);
    }
    $this->digest = fn (): string => WeekDigest::of($this->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02', 'America/Mexico_City'), 'America/Mexico_City'));
    $this->week = resolve(DisputeWeek::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-02', 'America/Mexico_City'), 'El martes salí a las 18:00, no a las 17:00.', $this->device->id, ($this->digest)());
});

function resolveDispute(object $test, string $note = 'Revisamos el lector: salió a las 17:00. Confirma de nuevo.'): WeeklyConfirmation
{
    return resolve(ResolveDispute::class)->handle($test->week->id, $test->hr->id, $note);
}

describe('resolving', function (): void {
    it('sends the disputed week back to pending with RH\'s note, keeping what was disputed, to the log and the audit trail without the note', function (): void {
        Log::spy();

        $week = resolveDispute($this, '  Revisamos el lector: salió a las 17:00. Confirma de nuevo.  ');

        expect($week->fresh())
            ->status->toBe(WeekStatus::Pending)
            ->reason->toBe('El martes salí a las 18:00, no a las 17:00.')
            ->resolution_note->toBe('Revisamos el lector: salió a las 17:00. Confirma de nuevo.')
            ->resolved_by->toBe($this->hr->id)
            ->credential_id->toBeNull()
            ->digest->toBeNull()
            ->and($week->fresh()?->resolved_at?->equalTo(now()))->toBeTrue();
        expect(AuditEntry::query()->where('event', 'attendance.week_dispute_resolved')->sole())
            ->subject_id->toBe($week->id)
            ->metadata->toEqual(['employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);
        Log::shouldHaveReceived('info')->with('attendance.week_dispute_resolved', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);
    });

    it('lets the person confirm the week again, leaving the dispute and its resolution behind', function (): void {
        resolveDispute($this);

        expect(resolve(ConfirmWeek::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-02', 'America/Mexico_City'), $this->device->id, ($this->digest)())->fresh())
            ->status->toBe(WeekStatus::Confirmed)
            ->reason->toBeNull()->disputed_at->toBeNull()
            ->resolution_note->toBeNull()->resolved_by->toBeNull()->resolved_at->toBeNull();
    });

    it('lets the person dispute the week again, with a new reason RH resolves anew', function (): void {
        resolveDispute($this);

        $again = resolve(DisputeWeek::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-02', 'America/Mexico_City'), 'Sigue mal: el miércoles también.', $this->device->id, ($this->digest)());

        expect($again->fresh())->status->toBe(WeekStatus::Disputed)->reason->toBe('Sigue mal: el miércoles también.')
            ->resolution_note->toBeNull()->resolved_by->toBeNull()->resolved_at->toBeNull()
            ->and(resolveDispute($this)->status)->toBe(WeekStatus::Pending);
    });

    it('does not resolve a week twice, nor one that is not disputed', function (): void {
        resolveDispute($this);

        expect(fn () => resolveDispute($this, 'Otra nota distinta de la primera.'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('not_disputed'));
        expect($this->week->fresh()?->resolution_note)->toBe('Revisamos el lector: salió a las 17:00. Confirma de nuevo.')
            ->and(AuditEntry::query()->where('event', 'attendance.week_dispute_resolved')->count())->toBe(1);
    });

    it('requires a note of at least 10 characters', function (string $note): void {
        expect(fn () => resolveDispute($this, $note))->toThrow(InvalidArgumentException::class);
        expect($this->week->fresh()?->status)->toBe(WeekStatus::Disputed);
    })->with(['empty' => [''], 'blank' => ['            '], 'too short' => ['Ya quedó']]);

    it('accepts only a member of the company', function (): void {
        $stranger = memberOf(Tenant::factory()->create(), TenantRole::Hr);
        workIn($this->tenant);

        expect(fn () => resolve(ResolveDispute::class)->handle($this->week->id, $stranger->id, 'Revisamos el lector y estaba bien.'))->toThrow(InvalidArgumentException::class);
        expect($this->week->fresh()?->status)->toBe(WeekStatus::Disputed);
    });
});

describe('from RH\'s panel', function (): void {
    it('resolves with the note and goes back with the news', function (): void {
        $this->actingAs($this->hr);

        $this->from(route('tenant.attendance.index', ['acme', 'status' => 'disputed']))
            ->post(route('tenant.attendance.weeks.resolve', ['acme', $this->week->id]), ['note' => 'Revisamos el lector: salió a las 17:00.'])
            ->assertRedirect(route('tenant.attendance.index', ['acme', 'status' => 'disputed']))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Objeción resuelta. La semana vuelve a esperar la respuesta de la persona.']);

        expect($this->week->fresh()?->status)->toBe(WeekStatus::Pending);
    });

    it('says so when the week was already resolved', function (): void {
        resolveDispute($this);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.weeks.resolve', ['acme', $this->week->id]), ['note' => 'Revisamos el lector: salió a las 17:00.'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta objeción ya se resolvió.']);
    });

    it('asks for the note', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.weeks.resolve', ['acme', $this->week->id]), ['note' => 'Ya'])->assertSessionHasErrors('note');

        expect($this->week->fresh()?->status)->toBe(WeekStatus::Disputed);
    });

    it('does not let a viewer resolve', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.attendance.weeks.resolve', ['acme', $this->week->id]), ['note' => 'Revisamos el lector: salió a las 17:00.'])->assertForbidden();

        expect($this->week->fresh()?->status)->toBe(WeekStatus::Disputed);
    });

    it('answers 404 to a week of another company, by its URL', function (): void {
        $globex = Tenant::factory()->create();
        $foreign = WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.weeks.resolve', ['acme', $foreign->id]), ['note' => 'Revisamos el lector: salió a las 17:00.'])->assertNotFound();

        expect(WeeklyConfirmation::query()->withoutGlobalScopes()->find($foreign->id)?->status)->toBe(WeekStatus::Disputed);
    });
});
