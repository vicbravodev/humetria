<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Tenancy\Models\Tenant;

it('does not read or change the periods and the ledger of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $periodOfB = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $b->id]);
    $entryOfB = LeaveEntry::query()->withoutGlobalScopes()->where('leave_entitlement_id', $periodOfB->id)->sole();

    workIn($a);
    expect(LeaveEntitlement::query()->count())->toBe(0)
        ->and(LeaveEntitlement::query()->find($periodOfB->id))->toBeNull()
        ->and(LeaveEntitlement::query()->whereKey($periodOfB->id)->update(['policy_name' => 'Tomada']))->toBe(0)
        ->and(LeaveEntry::query()->count())->toBe(0)
        ->and(LeaveEntry::query()->find($entryOfB->id))->toBeNull();

    workIn($b);
    expect(LeaveEntitlement::query()->count())->toBe(1)
        ->and(LeaveEntry::query()->count())->toBe(1);
});
