<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyWorkCenters;

mutates(CompanyWorkCenters::class);

it('pages the company\'s work centers in use, by name', function (): void {
    $tenant = Tenant::factory()->create();
    $north = WorkCenter::factory()->for($tenant)->create(['name' => 'Norte']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Centro']);
    WorkCenter::factory()->for($tenant)->archived()->create(['name' => 'Archivado']);
    WorkCenter::factory()->for(Tenant::factory()->create())->create(['name' => 'Ajeno']);
    workIn($tenant);

    $page = resolve(WorkCenters::class)->activePage(perPage: 1, pageName: 'centers');

    expect($page->total())->toBe(2)
        ->and($page->getPageName())->toBe('centers')
        ->and(array_map(fn (WorkCenterSummary $center): string => $center->name, $page->items()))->toBe(['Centro']);

    request()->query->set('centers', 2);

    expect(resolve(WorkCenters::class)->activePage(perPage: 1, pageName: 'centers')->items())
        ->toEqual([new WorkCenterSummary($north->id, 'Norte', false)]);
});

it('finds a work center in use of the company, and no archived or other company\'s one', function (): void {
    $tenant = Tenant::factory()->create();
    $north = WorkCenter::factory()->for($tenant)->create(['name' => 'Norte']);
    $archived = WorkCenter::factory()->for($tenant)->archived()->create();
    $other = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    workIn($tenant);

    expect(resolve(WorkCenters::class)->findActive($north->id))->toEqual(new WorkCenterSummary($north->id, 'Norte', false))
        ->and(resolve(WorkCenters::class)->findActive($archived->id))->toBeNull()
        ->and(resolve(WorkCenters::class)->findActive($other->id))->toBeNull();
});
