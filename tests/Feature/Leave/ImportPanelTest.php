<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Leave\Http\Controllers\OpeningBalanceController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\ImportPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;

mutates(OpeningBalanceController::class, ImportPages::class);

const LEAVE_IMPORT_HEADER = 'numero_de_empleado,aniversario,dias_pendientes';

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2026-01-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'employee_number' => 'A-001', 'hired_on' => '2022-05-10']);
    // Her sixth anniversary, 10 February 2026, falls after the tracking started.
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Caro Luz', 'employee_number' => 'C-003', 'hired_on' => '2020-02-10']);
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-03-20 18:00:00');
});

/**
 * @param  list<string>  $rows
 */
function leaveImportFile(array $rows, string $header = LEAVE_IMPORT_HEADER): UploadedFile
{
    return UploadedFile::fake()->createWithContent('saldos.csv', implode("\n", [$header, ...$rows])."\n");
}

/**
 * @return array<string, mixed> what the check flashed for the page
 */
function leaveImportFlash(): array
{
    $flash = session(SessionKey::FLASH_DATA);

    return is_array($flash) && is_array($flash['leave_import'] ?? null) ? $flash['leave_import'] : [];
}

/**
 * @param  array<string, mixed>  $extra
 */
function leaveImportPost(string $route, UploadedFile $file, array $extra = []): Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->hr)->post(route($route, test()->tenant), ['file' => $file, ...$extra]);
}

it('lives under the vacations of the company', function (): void {
    expect(route('tenant.vacations.import.show', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/import')
        ->and(route('tenant.vacations.import.plan', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/import/plan')
        ->and(route('tenant.vacations.import.store', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/import')
        ->and(route('tenant.vacations.import.template', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/import/template');
});

it('opens for owner and hr with the limit of rows, and is closed to a viewer', function (): void {
    config(['leave.import_max_rows' => 150]);

    $props = $this->actingAs($this->hr)->get(route('tenant.vacations.import.show', $this->tenant))->assertOk()->inertiaProps();

    expect($props['maxRows'])->toBe(150);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.vacations.import.show', $this->tenant))->assertForbidden();
});

it('gives a template with the columns of the file', function (): void {
    $response = $this->actingAs($this->hr)->get(route('tenant.vacations.import.template', $this->tenant))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('plantilla-saldos-iniciales.csv')
        ->and(explode("\n", (string) $response->getContent())[0])->toStartWith(LEAVE_IMPORT_HEADER);
});

it('checks the file and writes nothing until it is confirmed', function (): void {
    leaveImportPost('tenant.vacations.import.plan', leaveImportFile(['A-001,2025,10']))->assertRedirect();

    expect(leaveImportFlash())->toMatchArray(['ready' => 1, 'rejected' => 0, 'hiddenRejected' => 0])
        ->and(leaveImportFlash()['readyRows'])->toBe([['line' => 2, 'name' => 'Ana Solís', 'year' => 2025, 'days' => 10]])
        ->and(LeaveEntitlement::query()->count())->toBe(0)
        ->and(LeaveEntry::query()->count())->toBe(0);
});

it('says row by row, in Spanish, why a row cannot come in', function (string $row, string $reason): void {
    leaveImportPost('tenant.vacations.import.plan', leaveImportFile([$row]))->assertRedirect();

    expect(leaveImportFlash())->toMatchArray(['ready' => 0, 'rejected' => 1])
        ->and(leaveImportFlash()['rejectedRows'])->toBe([['line' => 2, 'reason' => $reason]]);
})->with([
    'an unknown employee' => ['Z-999,2025,5', 'No hay una persona con ese número de empleado.'],
    'more days than the period gave' => ['A-001,2025,17', 'Son más días de los que daba ese periodo.'],
    'a period the system grants itself' => ['C-003,2026,5', 'Ese periodo lo otorga el sistema desde la fecha de arranque.'],
    'a year that is not an anniversary' => ['A-001,2022,5', 'Ese año no es un aniversario que ya haya pasado.'],
    'days that are not a number' => ['A-001,2025,muchos', 'Los días pendientes deben ser un número entero.'],
]);

it('says a period that is already loaded or repeated in the file', function (): void {
    leaveImportPost('tenant.vacations.import.store', leaveImportFile(['A-001,2025,10']))->assertRedirect();

    leaveImportPost('tenant.vacations.import.plan', leaveImportFile(['A-001,2025,10', 'A-001,2024,5', 'A-001,2024,6']))->assertRedirect();

    expect(array_column(leaveImportFlash()['rejectedRows'], 'reason'))->toBe(['Ese periodo ya está cargado.', 'El archivo repite ese periodo.'])
        ->and(leaveImportFlash()['ready'])->toBe(1);
});

it('loads the ready rows on confirming and tells what stayed out', function (): void {
    leaveImportPost('tenant.vacations.import.store', leaveImportFile(['A-001,2025,10', 'Z-999,2025,5']))
        ->assertRedirect(route('tenant.vacations.balances.index', $this->tenant));

    $period = LeaveEntitlement::query()->sole();

    expect($period->only(['employee_id', 'years_of_service']))->toBe(['employee_id' => $this->ana->id, 'years_of_service' => 3])
        ->and(LeaveEntry::query()->sum('days'))->toBe(10)
        ->and(AuditEntry::query()->where('event', 'leave.opening_balances_imported')->count())->toBe(1);
});

it('plans again from the file on confirming: nothing sent besides it can write a rejected row', function (): void {
    $forged = ['ready' => [['line' => 2, 'employeeId' => $this->ana->id, 'years' => 3, 'anniversary' => '2025-05-10', 'days' => 99]], 'rows' => ['A-001,2025,99'], 'confirm' => '1', 'days' => 99];

    leaveImportPost('tenant.vacations.import.store', leaveImportFile(['A-001,2025,99']), $forged)->assertRedirect();

    expect(LeaveEntitlement::query()->count())->toBe(0)->and(LeaveEntry::query()->count())->toBe(0);
});

it('does not duplicate a period when the same file is loaded again', function (): void {
    foreach ([1, 2] as $_) {
        leaveImportPost('tenant.vacations.import.store', leaveImportFile(['A-001,2025,10']))->assertRedirect();
    }

    expect(LeaveEntitlement::query()->count())->toBe(1)->and(LeaveEntry::query()->sum('days'))->toBe(10);
});

it('refuses a file of more rows than the limit, without writing', function (): void {
    config(['leave.import_max_rows' => 2]);

    leaveImportPost('tenant.vacations.import.store', leaveImportFile(['A-001,2025,1', 'A-001,2024,1', 'A-001,2023,1']))->assertSessionHasErrors('file');

    expect(LeaveEntitlement::query()->count())->toBe(0);
});

it('refuses a file without the columns of the template, or without a file', function (): void {
    leaveImportPost('tenant.vacations.import.plan', leaveImportFile(['A-001,2025'], 'numero_de_empleado,aniversario'))
        ->assertSessionHasErrors(['file' => 'Falta la columna «dias_pendientes». Usa la plantilla.']);
    $this->actingAs($this->hr)->post(route('tenant.vacations.import.plan', $this->tenant), [])->assertSessionHasErrors('file');
    $this->actingAs($this->hr)->post(route('tenant.vacations.import.store', $this->tenant), [])->assertSessionHasErrors('file');
});

it('lists at most 50 rejected rows and says how many more there are', function (): void {
    $rows = array_map(fn (int $number): string => "Z-{$number},2025,1", range(1, 60));

    leaveImportPost('tenant.vacations.import.plan', leaveImportFile($rows))->assertRedirect();

    expect(leaveImportFlash())->toMatchArray(['rejected' => 60, 'hiddenRejected' => 10])->and(leaveImportFlash()['rejectedRows'])->toHaveCount(50);
});

it('never places a person of another company', function (): void {
    $other = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $other->id, 'employee_number' => 'B-777', 'hired_on' => '2022-05-10']);

    leaveImportPost('tenant.vacations.import.store', leaveImportFile(['B-777,2025,5']))->assertRedirect();

    expect(LeaveEntitlement::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('lets a viewer do none of it', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    foreach (['tenant.vacations.import.plan', 'tenant.vacations.import.store'] as $route) {
        $this->actingAs($viewer)->post(route($route, $this->tenant), ['file' => leaveImportFile(['A-001,2025,10'])])->assertForbidden();
    }

    expect(LeaveEntitlement::query()->count())->toBe(0);
});
