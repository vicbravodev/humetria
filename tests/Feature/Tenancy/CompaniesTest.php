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

    expect($company)->toEqual(new CompanySummary($tenant->id, 'Acme', $tenant->slug, blocked: false))
        ->and(resolve(CurrentTenant::class)->id())->toBe($tenant->id)
        ->and(WorkCenter::query()->pluck('name')->all())->toBe(['Planta Norte']);
});

it('reports a company that may not be used as blocked', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->update(['restriction' => 'suspended', 'restriction_reason' => 'Falta de pago', 'restricted_at' => now()]);

    expect(resolve(Companies::class)->enter($tenant->id)->blocked)->toBe($tenant->status()->isBlocked())->toBeTrue();
});

it('rejects an id that is no company', function (string $id): void {
    resolve(Companies::class)->enter($id);
})->with(['0b6c3d3e-9a51-4f8a-9a0f-2f1d6e3b8c11', 'not-a-uuid'])->throws(InvalidArgumentException::class);

it('enters a company by its slug, and gives nothing for a slug that is no company', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);

    expect(resolve(Companies::class)->enterBySlug('no-existe'))->toBeNull()
        ->and(fn () => resolve(CurrentTenant::class)->id())->toThrow(App\Modules\Tenancy\Exceptions\NoCurrentTenant::class)
        ->and(resolve(Companies::class)->enterBySlug($tenant->slug))->toEqual(new CompanySummary($tenant->id, 'Acme', $tenant->slug, blocked: false))
        ->and(resolve(CurrentTenant::class)->id())->toBe($tenant->id);
});

it('enters no company by the slug of one that may not be used', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    expect(resolve(Companies::class)->enterBySlug($tenant->slug))->toBeNull()
        ->and(fn () => resolve(CurrentTenant::class)->id())->toThrow(App\Modules\Tenancy\Exceptions\NoCurrentTenant::class);
});

it('describes the company the code works in now', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($tenant);

    expect(resolve(Companies::class)->current())->toEqual(new CompanySummary($tenant->id, 'Acme', $tenant->slug, blocked: false));
});

it('has no current company outside one', function (): void {
    resolve(Companies::class)->current();
})->throws(App\Modules\Tenancy\Exceptions\NoCurrentTenant::class);

it('gives the owner\'s email as whom the people of the company write to, and nothing while it has no owner', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr)->update(['email' => 'rh@acme.mx']);
    resolve(Companies::class)->enter($tenant->id);

    expect(resolve(Companies::class)->contact())->toBeNull();

    ownerOf($tenant)->update(['email' => 'olga@acme.mx']);

    expect(resolve(Companies::class)->contact())->toBe('olga@acme.mx');
});
