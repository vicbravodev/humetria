<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyHeadcount;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyHeadcount::class);

it('counts the active people with no manager who lead nobody, as the chart leaves them out, never of another company', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $top = Employee::factory()->create(['tenant_id' => $a->id]);
    Employee::factory()->reportingTo($top)->create();
    Employee::factory()->count(2)->create(['tenant_id' => $a->id]);
    Employee::factory()->deactivated()->create(['tenant_id' => $a->id]);
    Employee::factory()->count(3)->create(['tenant_id' => $b->id]);
    workIn($a);

    expect(resolve(Headcount::class)->unplaced())->toBe(2);
});

it('counts nobody in a company without people', function (): void {
    workIn(Tenant::factory()->create());

    expect(resolve(Headcount::class)->unplaced())->toBe(0);
});
