<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\ArchiveWorkCenter;
use App\Modules\Tenancy\Actions\CreateWorkCenter;
use App\Modules\Tenancy\Actions\RestoreWorkCenter;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CreateWorkCenter::class, ArchiveWorkCenter::class, RestoreWorkCenter::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

/** A company whose plan covers `$centers` work centers, with `$used` of them in use. */
function centersFor(Tenant $tenant, int $centers, int $used = 0): void
{
    WorkCenter::factory()->count($used)->create(['tenant_id' => $tenant->id]);
    resolve(CompanyLimits::class)->sync($tenant->id, ['centers' => $centers]);
}

function centersUsed(Tenant $tenant): int
{
    return TenantLimit::query()->where('tenant_id', $tenant->id)->where('resource', 'centers')->firstOrFail()->used;
}

it('creates work centers up to the contracted ones and stops at the next, saying why and what to do', function (): void {
    centersFor($this->tenant, 2, 1);

    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm(['name' => 'Segunda']))->assertSessionHasNoErrors();
    expect(centersUsed($this->tenant))->toBe(2);

    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm(['name' => 'Tercera']))
        ->assertSessionHasErrors(['centers' => 'Tu plan cubre 2 centros de trabajo y ya los tienes todos. Pide una ampliación a Humetria para agregar otro.']);

    expect(WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(2)
        ->and(centersUsed($this->tenant))->toBe(2);
});

it('tells what one more center costs the plan', function (): void {
    [, $account] = paidCompany();
    $tenant = planFor($account, ['plan' => App\Modules\Billing\Enums\Plan::Integral, 'profile' => App\Modules\Billing\Enums\Profile::Direct, 'seats' => 100, 'centers' => 3]);
    centersFor($tenant, 3, 3);

    $this->actingAs(ownerOf($tenant))->post(route('tenant.work-centers.store', $tenant), workCenterForm())
        ->assertSessionHasErrors(['centers' => 'Tu plan cubre 3 centros de trabajo y ya los tienes todos. Pide una ampliación a Humetria para agregar otro. Con 4 centros de trabajo el importe pasa de $9,900 a $10,049 al mes, sin IVA.']);
});

it('does not limit a company without a plan', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm())->assertSessionHasNoErrors();

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('frees the place when a center is archived and takes it when it is restored, refusing when it is gone', function (): void {
    centersFor($this->tenant, 1, 1);
    $first = WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

    $this->actingAs($this->owner)->post(route('tenant.work-centers.archive', [$this->tenant, $first->id]))->assertSessionHasNoErrors();
    expect(centersUsed($this->tenant))->toBe(0);

    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm(['name' => 'Nueva']))->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->post(route('tenant.work-centers.restore', [$this->tenant, $first->id]))->assertSessionHasErrors('centers');

    expect(reloaded($first)->archived_at)->not->toBeNull()
        ->and(centersUsed($this->tenant))->toBe(1);
});

it('restores a center when its place is free', function (): void {
    centersFor($this->tenant, 2, 1);
    $archived = WorkCenter::factory()->archived()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)->post(route('tenant.work-centers.restore', [$this->tenant, $archived->id]))->assertSessionHasNoErrors();

    expect(centersUsed($this->tenant))->toBe(2)->and(reloaded($archived)->archived_at)->toBeNull();
});

it('lets a company above its limit read, edit and archive, and only blocks adding', function (): void {
    centersFor($this->tenant, 3, 3);
    resolve(CompanyLimits::class)->sync($this->tenant->id, ['centers' => 2]);
    $first = WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

    $this->actingAs($this->owner)->get(route('tenant.work-centers.index', $this->tenant))->assertOk();
    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm())->assertSessionHasErrors('centers');
    $this->actingAs($this->owner)->post(route('tenant.work-centers.archive', [$this->tenant, $first->id]))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('tenant.work-centers.store', $this->tenant), workCenterForm())->assertSessionHasErrors('centers');

    expect(centersUsed($this->tenant))->toBe(2);
});

it('keeps each company to its own centers', function (): void {
    centersFor($this->tenant, 1, 1);
    $other = Tenant::factory()->create();
    centersFor($other, 1, 0);

    $this->actingAs(ownerOf($other))->post(route('tenant.work-centers.store', $other), workCenterForm())->assertSessionHasNoErrors();

    expect(centersUsed($this->tenant))->toBe(1)->and(centersUsed($other))->toBe(1);
});

it('tells the company how many of the centers of its plan it uses, on its centers page', function (): void {
    centersFor($this->tenant, 3, 2);

    $this->actingAs($this->owner)->get(route('tenant.work-centers.index', $this->tenant))->assertOk()->assertInertia(fn ($page) => $page
        ->where('plan_limit', ['used' => 2, 'amount' => 3, 'remaining' => 1, 'over' => false]));
});

it('has no limit to show a company without a plan on its centers page', function (): void {
    $this->actingAs($this->owner)->get(route('tenant.work-centers.index', $this->tenant))->assertOk()->assertInertia(fn ($page) => $page->where('plan_limit', null));
});
