<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\AreaOptionsController;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(AreaOptionsController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

it('finds the company\'s areas by name for any member, at most 20, by name', function (): void {
    foreach (['Ventas norte', 'Ventas sur', 'Producción', '50% Ventas'] as $name) {
        Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
    }
    Area::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Ventas de otra empresa']);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.areas.options', [$this->tenant, 'q' => ' VENTAS ']))
        ->assertOk()
        ->assertExactJson([
            ['value' => Area::query()->withoutGlobalScopes()->where('name', '50% Ventas')->sole()->id, 'label' => '50% Ventas'],
            ['value' => Area::query()->withoutGlobalScopes()->where('name', 'Ventas norte')->sole()->id, 'label' => 'Ventas norte'],
            ['value' => Area::query()->withoutGlobalScopes()->where('name', 'Ventas sur')->sole()->id, 'label' => 'Ventas sur'],
        ]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.areas.options', [$this->tenant, 'q' => '%']))
        ->assertJsonCount(1);
});

it('gives the first 20 areas when nothing is typed', function (): void {
    Area::factory()->count(21)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.areas.options', $this->tenant))
        ->assertJsonCount(20);
});

it('is only for members of the company', function (): void {
    $this->getJson(route('tenant.areas.options', $this->tenant))->assertUnauthorized();
    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))
        ->getJson(route('tenant.areas.options', $this->tenant))
        ->assertNotFound();
});

it('finds areas ignoring accents', function (): void {
    Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->getJson(route('tenant.areas.options', [$this->tenant, 'q' => 'produccion']))
        ->assertJsonPath('0.label', 'Producción');
});
