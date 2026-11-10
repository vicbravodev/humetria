<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanySeniority;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(CompanySeniority::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->hired = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2024-03-15']);
    $this->recognized = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2024-03-15', 'seniority_on' => '2019-01-10']);
    $this->undated = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->seniority = resolve(Seniority::class);
});

it('starts from the hire date, or from the recognized seniority when there is one', function (): void {
    $other = Employee::factory()->create(['hired_on' => '2020-02-02']);
    $this->expectsDatabaseQueryCount(1);

    $dates = $this->seniority->startDatesFor([$this->hired->id, $this->recognized->id, $this->undated->id, $other->id]);

    expect(array_keys($dates))->toEqualCanonicalizing([$this->hired->id, $this->recognized->id])
        ->and($dates[$this->hired->id])->toBeInstanceOf(CarbonImmutable::class)
        ->and($dates[$this->hired->id]->toDateTimeString())->toBe('2024-03-15 00:00:00')
        ->and($dates[$this->recognized->id]->toDateTimeString())->toBe('2019-01-10 00:00:00');
});

it('answers only for the people asked', function (): void {
    expect(array_keys($this->seniority->startDatesFor([$this->hired->id])))->toBe([$this->hired->id]);
});

it('asks the database nothing for nobody', function (): void {
    $this->expectsDatabaseQueryCount(0);

    expect($this->seniority->startDatesFor([]))->toBe([]);
});
