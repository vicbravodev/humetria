<?php

declare(strict_types=1);

use App\Modules\Complaints\Support\ExcludingPermissions;
use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;

mutates(ExcludingPermissions::class);

// Who holds the permissions that keep someone from a kind of case is asked once per request (criterio C-45).
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    workIn($this->tenant);
});

it('asks once however many times it is read, and sees a permission given afterwards', function (): void {
    $excluding = resolve(ExcludingPermissions::class);
    $first = $excluding->byMember();

    DB::enableQueryLog();
    $excluding->byMember();
    $repeated = count(DB::getQueryLog());
    DB::disableQueryLog();

    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->viewer->id)->sole(), ['manage-organization'], $this->owner);

    expect($first)->toHaveKey($this->owner->id)->not->toHaveKey($this->viewer->id)
        ->and($repeated)->toBe(0)
        ->and($excluding->byMember())->toHaveKey($this->viewer->id);
});
