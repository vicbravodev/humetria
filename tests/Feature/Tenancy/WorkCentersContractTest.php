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
