<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Support\CompanyAreas;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyAreas::class);

it('names an area of the company, and no other company\'s', function (): void {
    $tenant = Tenant::factory()->create();
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    $other = Area::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    workIn($tenant);

    expect(resolve(Areas::class)->name($sales->id))->toBe('Ventas')
        ->and(resolve(Areas::class)->name($other->id))->toBeNull();
});
