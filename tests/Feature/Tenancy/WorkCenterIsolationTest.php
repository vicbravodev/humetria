<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantContext;

mutates(WorkCenter::class, TenantContext::class);

/**
 * Company A never reads nor changes company B's work centers through a query.
 * Through a URL, the controller tests check the 404.
 */
it('reads only the work centers of the current company', function (): void {
    $mine = WorkCenter::factory()->create(['name' => 'Mío']);
    WorkCenter::factory()->create(['name' => 'Ajeno']);

    workIn($mine->tenant);

    expect(WorkCenter::query()->pluck('name')->all())->toBe(['Mío'])
        ->and(WorkCenter::query()->find(WorkCenter::query()->withoutGlobalScopes()->where('name', 'Ajeno')->value('id')))->toBeNull();
});

it('cannot change the work centers of another company', function (): void {
    $mine = Tenant::factory()->create();
    $foreign = WorkCenter::factory()->create(['name' => 'Ajeno']);

    workIn($mine);

    expect(WorkCenter::query()->whereKey($foreign->id)->update(['name' => 'Robado']))->toBe(0)
        ->and(WorkCenter::query()->whereKey($foreign->id)->delete())->toBe(0)
        ->and(WorkCenter::query()->withoutGlobalScopes()->findOrFail($foreign->id)->name)->toBe('Ajeno');
});

it('refuses to read work centers when no company is resolved', function (): void {
    WorkCenter::factory()->create();

    WorkCenter::query()->get();
})->throws(NoCurrentTenant::class);

it('exposes the current company through the contract', function (): void {
    $tenant = Tenant::factory()->create();

    workIn($tenant);

    expect(resolve(CurrentTenant::class)->id())->toBe($tenant->id);
});

it('says there is no company before one is resolved', function (): void {
    resolve(CurrentTenant::class)->id();
})->throws(NoCurrentTenant::class);
