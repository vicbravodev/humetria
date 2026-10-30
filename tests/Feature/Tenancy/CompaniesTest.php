<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyDirectory;

mutates(CompanyDirectory::class);

it('enters a company by id, so its data is what the code reads', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    WorkCenter::factory()->for(Tenant::factory())->create(['name' => 'Ajena']);

    $company = resolve(Companies::class)->enter($tenant->id);

    expect($company)->toEqual(new CompanySummary($tenant->id, 'Acme', blocked: false))
        ->and(resolve(CurrentTenant::class)->id())->toBe($tenant->id)
        ->and(WorkCenter::query()->pluck('name')->all())->toBe(['Planta Norte']);
});

it('reports a company that may not be used as blocked', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->update(['deactivated_at' => now()]);

    expect(resolve(Companies::class)->enter($tenant->id)->blocked)->toBe($tenant->status()->isBlocked())->toBeTrue();
});

it('rejects an id that is no company', function (string $id): void {
    resolve(Companies::class)->enter($id);
})->with(['0b6c3d3e-9a51-4f8a-9a0f-2f1d6e3b8c11', 'not-a-uuid'])->throws(InvalidArgumentException::class);
