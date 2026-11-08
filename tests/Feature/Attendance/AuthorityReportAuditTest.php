<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\AuditReport;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    // A download sets these for the whole PHP process and may die with its output buffer open: put back after each test.
    $this->processState = [ignore_user_abort() === 1, (int) ini_get('max_execution_time'), ob_get_level()];
});

afterEach(function (): void {
    [$abort, $timeLimit, $buffers] = $this->processState;
    ignore_user_abort($abort);
    set_time_limit($timeLimit);
    while (ob_get_level() > $buffers) {
        ob_end_clean();
    }
});

it('writes every download to the audit trail before its first row, and logs how many rows went out', function (): void {
    Log::spy();

    $this->get(route('tenant.attendance.report.export', ['acme', ...$this->period]))->assertOk()->streamedContent();

    $entry = AuditEntry::query()->where('event', 'attendance.report_exported')->sole();
    $ids = ['action' => 'export', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'format' => 'csv'];
    // The database keeps the keys in its own order.
    expect($entry->metadata)->toEqual($ids)
        ->and(json_encode($entry->metadata))->not->toContain('Ana')->not->toContain('19.43');
    Log::shouldHaveReceived('info')->with('attendance.report_exported', $ids)->once();
    Log::shouldHaveReceived('info')->with('attendance.report_exported', [...$ids, 'rows' => 2, 'completed' => true])->once();
});

it('keeps the audit entry of a download cut off halfway', function (): void {
    // A second chunk of people fails: the first 200 rows went out already.
    $people = EmployeeFactory::new()->count(201)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $marks = [];
    foreach ($people as $person) {
        $marks[] = [
            'id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'employee_id' => $person->id, 'work_center_id' => $this->plant->id,
            'type' => MarkType::Start->value, 'method' => MarkMethod::QrAndGeofence->value, 'marked_at' => CarbonImmutable::parse('2027-01-26 08:00', 'America/Mexico_City')->utc(), 'created_at' => now(),
        ];
    }
    AttendanceMark::query()->insert($marks);
    // The company's roster, which fails on its second read of people by id: the second chunk of a report.
    $this->app->instance(Roster::class, new class(resolve(Roster::class)) implements Roster
    {
        private int $reads = 0;

        public function __construct(private readonly Roster $roster) {}

        public function activeIn(string $workCenterId): array
        {
            return $this->roster->activeIn($workCenterId);
        }

        public function activeIdsIn(string $workCenterId): array
        {
            return $this->roster->activeIdsIn($workCenterId);
        }

        public function activeIdsInMany(array $workCenterIds): array
        {
            return $this->roster->activeIdsInMany($workCenterIds);
        }

        public function find(string $employeeId): ?RosterEntry
        {
            return $this->roster->find($employeeId);
        }

        public function findMany(array $employeeIds): array
        {
            if (++$this->reads > 1) {
                throw new RuntimeException('The roster went away.');
            }

            return $this->roster->findMany($employeeIds);
        }

        public function page(RosterQuery $query, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
        {
            return $this->roster->page($query, $perPage, $pageName);
        }

        public function matchingIds(RosterQuery $query): array
        {
            return $this->roster->matchingIds($query);
        }
    });

    $outputs = fn (): int => count(array_filter(get_resources('stream'), fn ($stream): bool => (stream_get_meta_data($stream)['uri'] ?? '') === 'php://output'));

    expect(fn () => $this->get(route('tenant.attendance.report.export', ['acme', ...$this->period]))->streamedContent())->toThrow(RuntimeException::class, 'The roster went away.');
    // No handle on the output is left open behind the dead stream.
    expect($outputs())->toBe(0)
        ->and(AuditEntry::query()->where('event', 'attendance.report_exported')->sole()->metadata)->toEqual(['action' => 'export', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'format' => 'csv']);
});

it('writes a view of a center\'s report on its first page with no search, never on the next ones, a search or before a center', function (): void {
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period]))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period, 'q' => 'ana']))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period, 'page' => 2]))->assertOk();
    $this->get(route('tenant.attendance.report', ['acme']))->assertOk();

    expect(AuditEntry::query()->where('event', 'attendance.report_exported')->orderBy('id')->pluck('metadata')->all())->toEqual([
        ['action' => 'view', 'work_center_id' => $this->plant->id, 'from' => '2027-01-25', 'to' => '2027-01-31', 'rows' => 2, 'format' => 'page'],
    ]);
});

it('writes nothing for a request it refuses', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    $this->get(route('tenant.attendance.report.export', ['acme', ...$this->period]))->assertForbidden();
    $this->get(route('tenant.attendance.report', ['acme', ...$this->period]))->assertForbidden();

    expect(AuditEntry::query()->where('event', 'attendance.report_exported')->count())->toBe(0);
});
