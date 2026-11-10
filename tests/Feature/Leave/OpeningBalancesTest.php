<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Leave\Actions\ImportOpeningBalances;
use App\Modules\Leave\Actions\PlanOpeningBalances;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeavePolicyRow;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\OpeningCsv;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(PlanOpeningBalances::class, ImportOpeningBalances::class, OpeningCsv::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2026-01-01']);
    $this->user = User::factory()->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'A-001', 'hired_on' => '2022-05-10']);
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-03-20 18:00:00');
});

/**
 * @return array<string, string>
 */
function openingRow(string $number, string $year, string $days): array
{
    return ['numero_de_empleado' => $number, 'aniversario' => $year, 'dias_pendientes' => $days];
}

/**
 * @param  list<array<string, string>>  $rows
 */
function importOpening(array $rows): int
{
    return resolve(ImportOpeningBalances::class)->handle(resolve(PlanOpeningBalances::class)->handle($rows)['ready'], test()->user->id);
}

function openingFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'opening');
    file_put_contents($path, $content);

    return $path;
}

/**
 * @return array<string, list<string>>
 */
function openingFileErrors(string $content): array
{
    try {
        OpeningCsv::read(openingFile($content));
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('plans a valid row with the person, the year of service and the anniversary it means', function (): void {
    expect(resolve(PlanOpeningBalances::class)->handle([openingRow('A-001', '2025', '4')]))->toBe([
        'ready' => [['line' => 2, 'employeeId' => $this->ana->id, 'years' => 3, 'anniversary' => '2025-05-10', 'days' => 4]],
        'rejected' => [],
    ])->and(LeaveEntitlement::query()->count())->toBe(0);
});

it('creates the period of that year with what the law gave it, and what was pending as its opening entry', function (): void {
    expect(importOpening([openingRow('A-001', '2025', '4')]))->toBe(1);

    $period = LeaveEntitlement::query()->sole();
    expect($period->only(['tenant_id', 'employee_id', 'years_of_service', 'leave_policy_id', 'policy_name', 'premium_percent', 'statutory_days', 'extra_days', 'extra_expires_on', 'source']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 3, 'leave_policy_id' => null, 'policy_name' => 'Ley', 'premium_percent' => 25,
        'statutory_days' => 16, 'extra_days' => 0, 'extra_expires_on' => null, 'source' => 'opening',
    ])->and([$period->anniversary_on->toDateString(), $period->granted_on->toDateString(), $period->enjoy_by->toDateString(), $period->prescribes_on->toDateString()])
        ->toBe(['2025-05-10', '2026-03-20', '2025-11-10', '2026-11-10'])
        ->and(LeaveEntry::query()->get()->map->only(['tenant_id', 'employee_id', 'leave_entitlement_id', 'kind', 'tranche', 'days', 'recorded_by'])->all())->toBe([
            ['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $period->id, 'kind' => EntryKind::Opening, 'tranche' => Tranche::Statutory, 'days' => 4, 'recorded_by' => $this->user->id],
        ]);
});

it('counts a period from before 2023 with the table of its day', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'B-002', 'hired_on' => '2021-05-10']);

    importOpening([openingRow('B-002', '2022', '6'), openingRow('B-002', '2023', '12')]);

    expect(LeaveEntitlement::query()->orderBy('years_of_service')->get()->map(fn (LeaveEntitlement $period): array => [$period->years_of_service, $period->anniversary_on->toDateString(), $period->statutory_days])->all())
        ->toBe([[1, '2022-05-10', 6], [2, '2023-05-10', 14]]);
});

it('keeps a period of zero pending days as evidence, with no entry', function (): void {
    expect(importOpening([openingRow('A-001', '2025', '0')]))->toBe(1)
        ->and(LeaveEntitlement::query()->sole()->statutory_days)->toBe(16)
        ->and(LeaveEntry::query()->count())->toBe(0);
});

it('fills the statutory tranche first and puts the rest in the extra one, with the terms of the policy', function (): void {
    $policy = LeavePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Corporativo', 'premium_percent' => 40, 'extra_expires_months' => 24]);
    LeavePolicyRow::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id, 'year_from' => 1, 'year_to' => 5, 'days' => 20]);
    LeavePolicyAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'leave_policy_id' => $policy->id]);

    importOpening([openingRow('A-001', '2025', '18'), openingRow('A-001', '2024', '14')]);

    $third = LeaveEntitlement::query()->where('years_of_service', 3)->sole();
    $second = LeaveEntitlement::query()->where('years_of_service', 2)->sole();
    expect($third->only(['leave_policy_id', 'policy_name', 'premium_percent', 'statutory_days', 'extra_days']))->toBe(['leave_policy_id' => $policy->id, 'policy_name' => 'Corporativo', 'premium_percent' => 40, 'statutory_days' => 16, 'extra_days' => 4])
        ->and($third->extra_expires_on?->toDateString())->toBe('2027-05-10')
        ->and(LeaveEntry::query()->where('leave_entitlement_id', $third->id)->orderByDesc('days')->get()->map(fn (LeaveEntry $entry): array => [$entry->kind, $entry->tranche, $entry->days])->all())
        ->toBe([[EntryKind::Opening, Tranche::Statutory, 16], [EntryKind::Opening, Tranche::Extra, 2]])
        // Exactly the statutory days: nothing goes to the extra tranche.
        ->and(LeaveEntry::query()->where('leave_entitlement_id', $second->id)->get()->map(fn (LeaveEntry $entry): array => [$entry->tranche, $entry->days])->all())->toBe([[Tranche::Statutory, 14]]);
});

it('rejects each row that cannot come in, with its line and why', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'C-003', 'hired_on' => null]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'D-004', 'hired_on' => '2024-01-01']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-005', 'hired_on' => '2023-12-31']);
    Employee::factory()->create(['employee_number' => 'X-900', 'hired_on' => '2022-05-10']);
    LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2023-05-10']);

    $plan = resolve(PlanOpeningBalances::class)->handle([
        openingRow('Z-999', '2025', '4'),
        openingRow('X-900', '2025', '4'),
        openingRow('C-003', '2025', '4'),
        openingRow('A-001', '2022', '4'),
        openingRow('A-001', '2026', '4'),
        openingRow('A-001', 'dos mil', '4'),
        openingRow('D-004', '2026', '4'),
        openingRow('E-005', '2025', '4'),
        openingRow('A-001', '2024', '15'),
        openingRow('A-001', '2024', '-1'),
        openingRow('A-001', '2024', '2.5'),
        openingRow('A-001', '2024', ''),
        openingRow('A-001', '2025', '4'),
        openingRow('a-001', '2025', '5'),
        openingRow('A-001', '2023', '4'),
        openingRow('A-001', '2024', '14'),
    ]);

    expect($plan['rejected'])->toBe([
        ['line' => 2, 'reason' => 'unknown_employee'],
        ['line' => 3, 'reason' => 'unknown_employee'],
        ['line' => 4, 'reason' => 'no_start_date'],
        // The year of the hire is no anniversary, and the one of 2026 (10 May) has not come on 20 March.
        ['line' => 5, 'reason' => 'not_an_anniversary'],
        ['line' => 6, 'reason' => 'not_an_anniversary'],
        ['line' => 7, 'reason' => 'not_an_anniversary'],
        // On the day the company started: the daily command grants it.
        ['line' => 8, 'reason' => 'after_tracking_start'],
        // The second year gives 14 days.
        ['line' => 10, 'reason' => 'too_many_days'],
        ['line' => 11, 'reason' => 'invalid_days'],
        ['line' => 12, 'reason' => 'invalid_days'],
        ['line' => 13, 'reason' => 'invalid_days'],
        ['line' => 15, 'reason' => 'duplicate_in_file'],
        ['line' => 16, 'reason' => 'already_loaded'],
    ])->and(array_map(fn (array $row): array => [$row['line'], $row['years'], $row['anniversary'], $row['days']], $plan['ready']))->toBe([
        // The day before the company started still comes in by file.
        [9, 2, '2025-12-31', 4],
        [14, 3, '2025-05-10', 4],
        [17, 2, '2024-05-10', 14],
    ]);
});

it('takes the anniversary of today and not the one of tomorrow', function (string $hiredOn, array $reasons): void {
    LeaveSetting::query()->update(['tracking_since' => '2026-06-01']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'F-006', 'hired_on' => $hiredOn]);

    expect(array_column(resolve(PlanOpeningBalances::class)->handle([openingRow('F-006', '2026', '1')])['rejected'], 'reason'))->toBe($reasons);
})->with([
    'anniversary today' => ['2024-03-20', []],
    'anniversary tomorrow' => ['2024-03-21', ['not_an_anniversary']],
]);

it('uses the line of the file when the row brings it, and trims what a spreadsheet pads', function (): void {
    $plan = resolve(PlanOpeningBalances::class)->handle([['line' => '7', ...openingRow(' A-001 ', ' 2025 ', ' 4 ')], ['line' => '9', ...openingRow('Z-999', '2025', '4')]]);

    expect($plan['ready'][0]['line'])->toBe(7)
        ->and($plan['ready'][0]['days'])->toBe(4)
        ->and($plan['rejected'])->toBe([['line' => 9, 'reason' => 'unknown_employee']]);
});

it('loads nothing twice: the same file again is all already loaded', function (): void {
    $rows = [openingRow('A-001', '2025', '4'), openingRow('A-001', '2024', '0')];
    importOpening($rows);

    $again = resolve(PlanOpeningBalances::class)->handle($rows);

    expect($again)->toBe(['ready' => [], 'rejected' => [['line' => 2, 'reason' => 'already_loaded'], ['line' => 3, 'reason' => 'already_loaded']]])
        ->and(importOpening($rows))->toBe(0)
        ->and(LeaveEntitlement::query()->count())->toBe(2)
        ->and(LeaveEntry::query()->count())->toBe(1);
});

it('does not load a period that came in between the plan and the import', function (): void {
    $ready = resolve(PlanOpeningBalances::class)->handle([openingRow('A-001', '2025', '4'), openingRow('A-001', '2024', '3')])['ready'];
    importOpening([openingRow('A-001', '2025', '9')]);

    expect(resolve(ImportOpeningBalances::class)->handle($ready, $this->user->id))->toBe(1)
        ->and(LeaveEntitlement::query()->count())->toBe(2)
        ->and(LeaveEntry::query()->where('days', 9)->count())->toBe(1)
        ->and(LeaveEntry::query()->where('days', 4)->count())->toBe(0);
});

it('does not plan with the people or the periods of another company', function (): void {
    $other = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $other->id, 'tracking_since' => '2026-01-01']);
    importOpening([openingRow('A-001', '2025', '4')]);

    workIn($other);
    expect(resolve(PlanOpeningBalances::class)->handle([openingRow('A-001', '2025', '4')])['rejected'])->toBe([['line' => 2, 'reason' => 'unknown_employee']])
        ->and(LeaveEntitlement::query()->count())->toBe(0);
});

it('audits and logs the load once, with counts only', function (): void {
    Log::spy();

    importOpening([openingRow('A-001', '2025', '4'), openingRow('A-001', '2024', '3')]);

    Log::shouldHaveReceived('info')->with('leave.opening_balances_imported', ['periods' => 2, 'days' => 7])->once();
    $audit = AuditEntry::query()->where('event', 'leave.opening_balances_imported')->sole();
    expect($audit->tenant_id)->toBe($this->tenant->id)
        ->and($audit->metadata)->toEqual(['periods' => 2, 'days' => 7]);
});

it('writes nothing, not even to the audit trail, when nothing comes in', function (): void {
    Log::spy();

    expect(resolve(ImportOpeningBalances::class)->handle([], $this->user->id))->toBe(0)
        ->and(AuditEntry::query()->where('event', 'leave.opening_balances_imported')->count())->toBe(0);
    Log::shouldNotHaveReceived('info');
});

it('reads the file as a spreadsheet saves it, with the line of each row', function (string $content): void {
    expect(OpeningCsv::read(openingFile($content)))->toBe([
        ['line' => '2', 'numero_de_empleado' => 'A-001', 'aniversario' => '2025', 'dias_pendientes' => '4'],
        ['line' => '4', 'numero_de_empleado' => 'B-002', 'aniversario' => '2024', 'dias_pendientes' => '0'],
    ]);
})->with([
    'commas' => ["numero_de_empleado,aniversario,dias_pendientes\nA-001,2025,4\n\nB-002,2024,0\n"],
    'Excel: byte order mark, semicolons, CRLF and headers as a person types them' => ["\u{FEFF}Número de empleado;Aniversario;Días pendientes\r\nA-001; 2025 ;4\r\n;;\r\nB-002;2024;0\r\n"],
    'extra columns and quotes' => ["nombre,numero_de_empleado,aniversario,dias_pendientes\n\"López, Ana\",A-001,2025,4\n,,,\nBeto,B-002,2024,0,sobra"],
]);

it('reads a file saved in the Windows encoding', function (): void {
    $content = mb_convert_encoding("Número de empleado,Aniversario,Días pendientes\nÑ-001,2025,4\n", 'Windows-1252', 'UTF-8');

    expect(OpeningCsv::read(openingFile($content)))->toBe([['line' => '2', 'numero_de_empleado' => 'Ñ-001', 'aniversario' => '2025', 'dias_pendientes' => '4']]);
});

it('refuses the whole file when a column is missing, it is empty or it brings too many rows', function (string $content, string $message): void {
    config()->set('leave.import_max_rows', 2);

    expect(openingFileErrors($content))->toBe(['file' => [$message]]);
})->with([
    'missing column' => ["numero_de_empleado,aniversario\nA-001,2025\n", 'Falta la columna «dias_pendientes». Usa la plantilla.'],
    'no rows' => ["numero_de_empleado,aniversario,dias_pendientes\n\n", 'El archivo no tiene filas.'],
    'empty' => ['', 'Falta la columna «numero_de_empleado». Usa la plantilla.'],
    'too many rows' => ["numero_de_empleado,aniversario,dias_pendientes\nA,2025,1\nB,2025,1\nC,2025,1\n", 'El archivo tiene más de 2 filas. Divídelo en varios.'],
]);

it('takes a file with exactly the most rows allowed, 2,000 unless configured', function (): void {
    expect(config('leave.import_max_rows'))->toBe(2000);
    config()->set('leave.import_max_rows', 2);

    expect(OpeningCsv::read(openingFile("numero_de_empleado,aniversario,dias_pendientes\nA,2025,1\nB,2025,1\n")))->toHaveCount(2);
});

it('refuses a file of 2,001 rows whole', function (): void {
    $content = "numero_de_empleado,aniversario,dias_pendientes\n".str_repeat("A-001,2025,4\n", 2001);

    expect(openingFileErrors($content))->toBe(['file' => ['El archivo tiene más de 2,000 filas. Divídelo en varios.']]);
});
