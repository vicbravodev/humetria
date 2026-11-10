<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->logPath = storage_path('logs/leave-privacy-'.getmypid().'.log');
    File::delete($this->logPath);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath, 'logging.channels.single.level' => 'debug']);
    Log::forgetChannel('single');
    Notification::fake();
});

afterEach(function (): void {
    File::delete($this->logPath);
});

it('logs no note, reason, leave type name or portal token along a whole flow: ask with a note, reject with a reason, adjust with a note', function (): void {
    // Wednesday 4 November 2026, noon in Mexico City.
    $this->travelTo('2026-11-04 18:00:00');
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $ana = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    $type = LeaveType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Licencia por tratamiento oncológico']);
    $token = resolve(IssuePortalLink::class)->handle($ana->id);

    // Ana asks for days and for a leave, each with a note of her own.
    $this->post(route('portal.vacations.requests.store', $token), ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'note' => 'Cita con mi psiquiatra'])->assertSessionHasNoErrors();
    $this->post(route('portal.vacations.requests.store', $token), ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-17', 'leave_type_id' => $type->id, 'note' => 'Quimioterapia de mi madre'])->assertSessionHasNoErrors();
    $this->get(route('portal.vacations.preview', [$token, 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-24', 'leave_type_id' => $type->id]))->assertOk();

    // RH rejects one with its reason, and adjusts the period with a note.
    $this->actingAs($hr)->post(route('tenant.vacations.requests.reject', [$tenant, LeaveRequest::query()->whereNull('leave_type_id')->sole()->id]), ['reason' => 'Cierre de mes: nómina y auditoría'])->assertSessionHasNoErrors();
    $this->post(route('tenant.vacations.employees.adjust', [$tenant, $ana]), ['period' => $period->id, 'tranche' => 'statutory', 'days' => 2, 'note' => 'Compensación por guardia del fin de semana'])->assertSessionHasNoErrors();
    $this->post(route('tenant.vacations.requests.cancel', [$tenant, LeaveRequest::query()->whereNotNull('leave_type_id')->sole()->id]))->assertSessionHasNoErrors();

    $log = File::get($this->logPath);
    $audit = AuditEntry::query()->get()->pluck('metadata')->toJson();
    expect($log)->toContain('"leave.request_created"')->toContain('"leave.request_rejected"')->toContain('"leave.request_cancelled"')
        ->and($audit)->not->toBe('[]');

    foreach (['Cita con mi psiquiatra', 'Quimioterapia de mi madre', 'Cierre de mes', 'nómina y auditoría', 'Compensación por guardia', 'oncológico', $token] as $secret) {
        expect($log)->not->toContain($secret)
            ->and($audit)->not->toContain($secret);
    }
});
