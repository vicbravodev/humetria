<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Contracts\MemberLookup;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyMemberLookup;
use App\Modules\Tenancy\Support\ModuleStates;

mutates(CompanyMemberLookup::class);

// What other modules may ask about a person before anyone is signed in: whether they are the owner and what they can do.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
});

it('says whether the person is the owner, and nothing about any other role', function (): void {
    $lookup = resolve(MemberLookup::class);

    expect($lookup->isOwner($this->tenant->id, $this->owner->id))->toBeTrue()
        ->and($lookup->isOwner($this->tenant->id, $this->hr->id))->toBeFalse()
        ->and($lookup->isOwner($this->tenant->id, $this->viewer->id))->toBeFalse();
});

it('says no owner for someone who belongs to another company', function (): void {
    expect(resolve(MemberLookup::class)->isOwner(Tenant::factory()->create()->id, $this->owner->id))->toBeFalse();
});

it('reads the permissions of many people as it reads them of each one', function (): void {
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->viewer->id)->sole(), ['view-vacations', 'manage-organization'], $this->owner);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);
    resolve(ModuleStates::class)->forget();
    $lookup = resolve(MemberLookup::class);
    $ids = [$this->owner->id, $this->hr->id, $this->viewer->id];
    $many = $lookup->permissionsOfMany($this->tenant->id, $ids);

    foreach ($ids as $id) {
        expect($many[$id])->toEqualCanonicalizing($lookup->permissionsOf($this->tenant->id, $id));
    }

    expect($many[$this->viewer->id])->toContain('manage-organization')->not->toContain('view-vacations')
        ->and($lookup->permissionsOfMany($this->tenant->id, []))->toBe([]);
});
