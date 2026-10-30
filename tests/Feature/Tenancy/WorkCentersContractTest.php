<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterProfile;
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

it('describes a work center for a report, archived ones included, and no other company\'s one', function (): void {
    $tenant = Tenant::factory()->create(['legal_name' => 'Autopartes Acme, S.A. de C.V.', 'rfc' => 'AAC010101AB1']);
    $plant = WorkCenter::factory()->for($tenant)->archived()->create(workCenterForm(['interior_number' => null]));
    $other = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    workIn($tenant);

    expect(resolve(WorkCenters::class)->profile($plant->id))->toEqual(new WorkCenterProfile(
        id: $plant->id,
        name: 'Planta Querétaro',
        legalName: 'Autopartes Acme, S.A. de C.V.',
        rfc: 'AAC010101AB1',
        address: 'Av. 5 de Febrero 1200, col. Zona Industrial Benito Juárez, C.P. 76120, Querétaro, Querétaro',
        mainActivity: 'Manufactura de autopartes',
    ))->and(resolve(WorkCenters::class)->profile($other->id))->toBeNull();
});
