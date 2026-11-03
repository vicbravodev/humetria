<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Support\CompanyTeleworkStanding;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Str;

mutates(CompanyTeleworkStanding::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $this->foreign = WorkCenter::factory()->create(['tenant_id' => $this->b->id]);
    workIn($this->a);
});

it('tells where each row of the inspection table stands in a work center, in the table order', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]);
    Teleworker::factory()->active()->create(['tenant_id' => $this->a->id, 'employee_id' => $employee->id]);

    $rows = resolve(TeleworkStanding::class)->forCenters([$this->center->id])[$this->center->id];

    expect($rows)->not->toBeEmpty()
        ->and($rows[0])->toBeInstanceOf(TeleworkRow::class)
        ->and($rows[0]->numeral)->toBe('5.1')
        ->and($rows[0]->state)->toBe('done')
        ->and($rows[0]->stateLabel)->toBe('Cumple')
        ->and($rows[0]->detail)->toBe('1 persona en el padrón.');
});

it('answers for a center of another company, or one that does not exist, as for none', function (): void {
    $standing = resolve(TeleworkStanding::class)->forCenters([$this->foreign->id, (string) Str::uuid(), $this->center->id]);

    expect(array_keys($standing))->toBe([$this->center->id]);
});

it('answers nothing for no centers', function (): void {
    expect(resolve(TeleworkStanding::class)->forCenters([]))->toBe([]);
});

it('carries no name, health or document of a person: only the numeral, what it asks, its state and a count', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id, 'name' => 'Zacarías Quintanilla']);
    Teleworker::factory()->active()->create(['tenant_id' => $this->a->id, 'employee_id' => $employee->id]);

    $json = json_encode(resolve(TeleworkStanding::class)->forCenters([$this->center->id]), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Zacarías')->not->toContain('Quintanilla')->not->toContain('employee');
});
