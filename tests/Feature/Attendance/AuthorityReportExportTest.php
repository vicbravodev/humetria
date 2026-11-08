<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\ReportController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\AuthorityCsv;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

mutates(ReportController::class, AuthorityCsv::class);

/** The CSV's header row, as a spreadsheet reads it. */
const AUTHORITY_CSV_HEADER = 'Persona,Puesto,Fecha,Inicio,Fin,Fecha de fin,Pausas (min),Trabajado (min),Trabajado (h:mm),Ordinarias (min),Ordinarias (h:mm),'
    .'Extra doble (min),Extra doble (h:mm),Extra triple (min),Extra triple (h:mm),Día de descanso (min),Día de descanso (h:mm),'
    .'Evidencia,Correcciones,Detalle de correcciones,Conformidad,Huella de la semana confirmada,Avisos';

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-02-01 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** A mark of `$person` at a local time of Mexico City, taken with a device of theirs at the plant's door. */
function csvMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id, 'type' => $type,
        'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'latitude' => '19.432712', 'longitude' => '-99.133298',
        'accuracy_m' => 12.5, 'distance_m' => 11.25,
        'device_id' => AttendanceDevice::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id])->id,
    ]);
}

function exportUrl(array $query, string $tenant = 'acme'): string
{
    return route('tenant.attendance.report.export', [$tenant, ...$query]);
}

/** The period of the first full week of 2027 in the plant. */
function plantWeek(object $test, array $query = []): array
{
    return ['center' => $test->plant->id, 'from' => '2027-01-04', 'to' => '2027-01-10', ...$query];
}

describe('the file', function (): void {
    it('gives a spreadsheet one row per person-day, with local dates and times, minutes and h:mm', function (): void {
        // 23:50 in Mexico City is 05:50 UTC of Thursday: the day is Wednesday's.
        csvMark($this->ana, MarkType::Start, '2027-01-06 23:50');
        csvMark($this->ana, MarkType::BreakStart, '2027-01-07 03:00');
        csvMark($this->ana, MarkType::BreakEnd, '2027-01-07 03:30');
        csvMark($this->ana, MarkType::End, '2027-01-07 08:20');
        $original = csvMark($this->ana, MarkType::Start, '2027-01-08 08:15');
        AttendanceMark::factory()->correcting($original)->create(['marked_at' => CarbonImmutable::parse('2027-01-08 08:00', 'America/Mexico_City')->utc(), 'reason' => 'Llegó a las 8, el lector falló.']);
        WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2027-01-04']);
        $this->actingAs($this->hr);

        $response = $this->get(exportUrl(plantWeek($this)))->assertOk();

        expect($response->streamedContent())->toBe("\u{FEFF}".AUTHORITY_CSV_HEADER."\n"
            .'Ana López,Operadora,2027-01-06,23:50,08:20,2027-01-07,30,480,8:00,480,8:00,0,0:00,0,0:00,0,0:00,Fuerte,0,,objetada,,'."\n"
            .'Ana López,Operadora,2027-01-08,08:00,,,0,0,0:00,0,0:00,0,0:00,0,0:00,0,0:00,Corregida,1,"hora corregida de 08:15 a 08:00 (por RH): Llegó a las 8, el lector falló.",objetada,,Sin marca de fin'."\n");
    });

    it('sends it as a download nobody keeps, named after the center and the period with safe characters only', function (): void {
        $this->plant->update(['name' => 'Planta "Norte" / Ñuñoa']);
        $this->actingAs($this->hr);

        $response = $this->get(exportUrl(plantWeek($this)))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
            ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename=registro-jornada-planta-norte-nunoa-2027-01-04-2027-01-10.csv')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('gives only the header for a period with no marks', function (): void {
        csvMark($this->ana, MarkType::Start, '2027-01-12 08:00');
        $this->actingAs($this->hr);

        expect($this->get(exportUrl(plantWeek($this)))->assertOk()->streamedContent())->toBe("\u{FEFF}".AUTHORITY_CSV_HEADER."\n");
    });

    it('neutralizes a cell a spreadsheet would run as a formula', function (): void {
        $this->ana->update(['name' => '=HYPERLINK("http://x","y")', 'job_title' => '@SUM(1+1)']);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => "\tBob", 'job_title' => '-2+3']);
        foreach ([$this->ana, $bob] as $person) {
            $start = csvMark($person, MarkType::Start, '2027-01-05 08:00');
            csvMark($person, MarkType::End, '2027-01-05 16:00');
        }
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::BreakStart,
            'marked_at' => CarbonImmutable::parse('2027-01-05 12:00', 'America/Mexico_City')->utc(), 'method' => MarkMethod::Correction,
            'corrected_by' => User::factory(), 'reason' => '+cmd|/C calc',
        ]);
        $this->actingAs($this->hr);

        $body = $this->get(exportUrl(plantWeek($this)))->streamedContent();

        // A quote first: the spreadsheet shows the text as it is and runs nothing. A reason is never first in its cell.
        expect($body)->toContain("\n\"'\tBob\",'-2+3,2027-01-05,")
            ->toContain("\n\"'=HYPERLINK(\"\"http://x\"\",\"\"y\"\")\",'@SUM(1+1),2027-01-05,")
            ->toContain(',1,"marca agregada (inicio de pausa 12:00, por RH): +cmd|/C calc",')
            ->not->toContain("\n=")->not->toContain(',=')->not->toContain(',@')->not->toContain(',-2');
    });

    it('never carries where a mark was taken nor the device', function (): void {
        $mark = csvMark($this->ana, MarkType::Start, '2027-01-05 08:00');
        csvMark($this->ana, MarkType::End, '2027-01-05 16:00');
        $this->actingAs($this->hr);

        expect($this->get(exportUrl(plantWeek($this)))->streamedContent())->not->toContain('19.43')->not->toContain('99.13')
            ->not->toContain('12.5')->not->toContain('11.25')->not->toContain((string) $mark->device_id)->not->toContain('lat');
    });
});

describe('who may download it', function (): void {
    it('refuses a viewer', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(exportUrl(plantWeek($this)))->assertForbidden();
    });

    it('answers 404 to a union', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(exportUrl(plantWeek($this), $union->slug))->assertNotFound();
    });

    it('answers 404 to a center archived, of another company or unknown', function (): void {
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($this->hr);

        $this->get(exportUrl(plantWeek($this, ['center' => $archived->id])))->assertNotFound();
        $this->get(exportUrl(plantWeek($this, ['center' => $foreign->id])))->assertNotFound();
        $this->get(exportUrl(plantWeek($this, ['center' => '9b2f6a52-0c4e-4f3b-8a51-6a1f4bdb2c11'])))->assertNotFound();
    });
});

describe('the period', function (): void {
    it('takes up to 366 days', function (): void {
        $this->actingAs($this->hr);

        $this->get(exportUrl(plantWeek($this, ['from' => '2027-01-01', 'to' => '2028-01-01'])))->assertOk();
    });

    it('refuses a longer period, one that ends before it starts, or no dates, in Spanish', function (array $query, array $errors): void {
        $this->actingAs($this->hr);

        // Back to the page the link was on, where the errors show under the fields.
        $this->from('/admin/acme/attendance')->get(exportUrl(plantWeek($this, $query)))
            ->assertRedirect('/admin/acme/attendance')
            ->assertSessionHasErrors($errors);
    })->with([
        '367 days' => [['from' => '2027-01-01', 'to' => '2028-01-02'], ['to' => 'El periodo puede ser de hasta 366 días.']],
        'the end before the start' => [['from' => '2027-01-10', 'to' => '2027-01-04'], ['to' => 'La fecha final no puede ser anterior a la inicial.']],
        'no dates' => [['from' => '', 'to' => ''], ['from' => 'El campo fecha inicial es obligatorio.', 'to' => 'El campo fecha final es obligatorio.']],
        'not a date' => [['from' => '2027-02-30'], ['from' => 'Escribe una fecha inicial válida.']],
        'no center' => [['center' => ''], ['center' => 'Elige un centro de trabajo.']],
    ]);
});
