<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\WorkCenterOptionsController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(WorkCenterOptionsController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

it('finds the company\'s work centers in use by name, ignoring accents, for any member', function (): void {
    $leon = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta León']);
    $north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
    WorkCenter::factory()->for($this->tenant)->archived()->create(['name' => 'Planta archivada']);
    WorkCenter::factory()->for(Tenant::factory()->create())->create(['name' => 'Planta ajena']);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.work-centers.options', [$this->tenant, 'q' => 'PLANTA']))
        ->assertExactJson([['value' => $leon->id, 'label' => 'Planta León'], ['value' => $north->id, 'label' => 'Planta Norte']]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.work-centers.options', [$this->tenant, 'q' => 'leon']))
        ->assertJsonPath('0.label', 'Planta León');
});

it('gives the first 20 when nothing is typed', function (): void {
    WorkCenter::factory()->for($this->tenant)->count(21)->create();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.work-centers.options', $this->tenant))
        ->assertJsonCount(20);
});

it('is only for members of the company', function (): void {
    $this->getJson(route('tenant.work-centers.options', $this->tenant))->assertUnauthorized();
    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))
        ->getJson(route('tenant.work-centers.options', $this->tenant))
        ->assertNotFound();
});
