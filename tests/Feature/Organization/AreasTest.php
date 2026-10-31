<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\FindOrCreateArea;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Support\CompanyAreas;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(CompanyAreas::class);

it('names an area of the company, and no other company\'s', function (): void {
    $tenant = Tenant::factory()->create();
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    $other = Area::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    workIn($tenant);

    expect(resolve(Areas::class)->name($sales->id))->toBe('Ventas')
        ->and(resolve(Areas::class)->name($other->id))->toBeNull();
});

it('names several areas of the company at once, and leaves out another company\'s', function (): void {
    $tenant = Tenant::factory()->create();
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    $plant = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Planta']);
    $other = Area::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    workIn($tenant);

    expect(resolve(Areas::class)->names([$sales->id, $plant->id, $other->id]))->toEqual([$sales->id => 'Ventas', $plant->id => 'Planta'])
        ->and(resolve(Areas::class)->names([]))->toBe([]);
});

it('logs an area only when it creates one', function (): void {
    $tenant = Tenant::factory()->create();
    Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    workIn($tenant);
    Log::spy();

    $existing = resolve(FindOrCreateArea::class)->handle('VENTAS');
    $created = resolve(FindOrCreateArea::class)->handle('Planta');

    expect($existing->name)->toBe('Ventas');
    Log::shouldHaveReceived('info')->once()->with('area.created', ['area_id' => $created->id]);
});
