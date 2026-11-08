<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\AuditReport;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;

mutates(AuditReport::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    foreach ([[MarkType::Start, '2027-01-26 08:00'], [MarkType::End, '2027-01-26 16:00'], [MarkType::Start, '2027-01-27 08:00']] as [$type, $local]) {
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $ana->id, 'work_center_id' => $this->plant->id, 'type' => $type,
            'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'latitude' => '19.432712', 'longitude' => '-99.133298',
        ]);
    }
    $this->period = ['center' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31'];
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
});

it('writes every download to the audit trail and the log, with ids and counts only', function (): void {
    Log::spy();

    $this->get(route('tenant.attendance.report.export', ['acme', ...$this->period]))->assertOk()->streamedContent();

    $entry = AuditEntry::query()->where('event', 'attendance.report_exported')->sole();
    $ids = ['action' => 'export', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'rows' => 2, 'format' => 'csv'];
    // The database keeps the keys in its own order.
    expect($entry->metadata)->toEqual($ids)
        ->and(json_encode($entry->metadata))->not->toContain('Ana')->not->toContain('19.43');
    Log::shouldHaveReceived('info')->once()->with('attendance.report_exported', $ids);
});

it('writes a view of a center\'s report on its first page, never on the next ones nor before a center', function (): void {
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period]))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period, 'q' => 'ana']))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period, 'page' => 2]))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme']))->assertOk();

    expect(AuditEntry::query()->where('event', 'attendance.report_exported')->orderBy('id')->pluck('metadata')->all())->toEqual([
        ['action' => 'view', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'rows' => 2, 'format' => 'page'],
        ['action' => 'view', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'rows' => 2, 'format' => 'page'],
    ]);
});

it('writes nothing for a request it refuses', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->get(route('tenant.attendance.report.export', ['acme', ...$this->period]))->assertForbidden();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period]))->assertForbidden();

    expect(AuditEntry::query()->where('event', 'attendance.report_exported')->count())->toBe(0);
});
