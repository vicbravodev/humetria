<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\UpdateEmployee;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(UpdateEmployee::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->office = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    $this->ana = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx',
        'mobile' => null, 'employee_number' => 'A-001', 'job_title' => 'Operadora', 'area_id' => Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción'])->id, 'job_grade_id' => null,
    ]);
    $this->production = $this->ana->area_id;
});

/** The form of the employee as it is, with what RH changes on top. */
function recordForm(Employee $person, array $overrides = []): array
{
    return [
        'name' => $person->name, 'email' => $person->email, 'mobile' => '', 'gender' => 'female', 'work_center_id' => $person->work_center_id,
        'area' => 'Producción', 'job_title' => $person->job_title, 'employee_number' => $person->employee_number,
        ...$overrides,
    ];
}

function updateRecord(array $overrides): void
{
    test()->actingAs(test()->owner)
        ->put(route('tenant.employees.update', [test()->tenant, test()->ana->id]), recordForm(test()->ana, $overrides))
        ->assertSessionHasNoErrors();
}

it('records in the audit trail the center, job title, area and level a person had and has, and nothing else about them', function (): void {
    updateRecord(['work_center_id' => $this->office->id, 'job_title' => 'Supervisora', 'area' => 'Almacén', 'job_category' => 'Operador', 'job_level' => 'A']);

    $entry = AuditEntry::query()->where('event', 'employee.updated')->sole();
    $area = Area::query()->where('name', 'Almacén')->sole();
    $grade = JobGrade::query()->sole();

    expect($entry->subject_id)->toBe($this->ana->id)
        ->and($entry->actor_id)->toBe($this->owner->id)
        ->and($entry->metadata)->toEqual([
            'employee_id' => $this->ana->id,
            'changes' => [
                'work_center_id' => ['before' => $this->plant->id, 'after' => $this->office->id],
                'job_title' => ['before' => 'Operadora', 'after' => 'Supervisora'],
                'area_id' => ['before' => $this->production, 'after' => $area->id],
                'job_grade_id' => ['before' => null, 'after' => $grade->id],
            ],
        ])
        ->and(auditContent($entry))->not->toContain('Ana')->not->toContain('ana@acme.mx')->not->toContain('A-001');
});

it('names only what changed', function (): void {
    updateRecord(['job_title' => 'Supervisora']);

    expect(AuditEntry::query()->where('event', 'employee.updated')->sole()->metadata['changes'])
        ->toEqual(['job_title' => ['before' => 'Operadora', 'after' => 'Supervisora']]);
});

it('records nothing when none of those four changed, even if other data did', function (): void {
    updateRecord(['email' => 'ana.lopez@acme.mx', 'employee_number' => 'A-002']);

    expect(AuditEntry::query()->where('event', 'employee.updated')->count())->toBe(0)
        ->and(Employee::query()->sole()->email)->toBe('ana.lopez@acme.mx');
});

it('records each change once, in the same transaction as the update', function (): void {
    updateRecord(['job_title' => 'Supervisora']);
    updateRecord(['job_title' => 'Supervisora']);
    $this->ana->refresh();
    updateRecord(['job_title' => 'Jefa de turno']);

    expect(AuditEntry::query()->where('event', 'employee.updated')->orderBy('created_at')->orderBy('id')->get()->map(fn (AuditEntry $entry): mixed => $entry->metadata['changes']['job_title']['after'])->sort()->values()->all())
        ->toBe(['Jefa de turno', 'Supervisora']);
});

it('keeps the person out of the audit entries of another company', function (): void {
    updateRecord(['job_title' => 'Supervisora']);
    $other = Tenant::factory()->create();

    expect(AuditEntry::query()->where('event', 'employee.updated')->where('tenant_id', $other->id)->count())->toBe(0)
        ->and(AuditEntry::query()->where('event', 'employee.updated')->sole()->tenant_id)->toBe($this->tenant->id);
});
