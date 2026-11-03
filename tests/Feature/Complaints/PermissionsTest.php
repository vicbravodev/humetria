<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;

it('gives each role the complaint permissions of the design', function (TenantRole $role, bool $manage, bool $handle): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('manage-complaint-channel'))->toBe($manage)
        ->and(Gate::allows('handle-complaints'))->toBe($handle);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, false, false],
    'committee' => [TenantRole::Committee, false, true],
]);

it('reads its settings from config/complaints.php', function (): void {
    expect(config('complaints.min_group'))->toBe(5)
        ->and(config('complaints.description.min'))->toBe(20)
        ->and(config('complaints.description.max'))->toBe(5000)
        ->and(config('complaints.retention.default_months'))->toBe(24)
        ->and(config('complaints.retention.min_months'))->toBe(12)
        ->and(config('complaints.retention.max_months'))->toBe(60)
        ->and(config('complaints.deadlines.acknowledge_days'))->toBe(3)
        ->and(config('complaints.deadlines.resolve_days'))->toBe(30)
        ->and(config('complaints.rate_limits.submissions_per_hour'))->toBe(5)
        ->and(config('complaints.rate_limits.follow_ups_per_hour'))->toBe(30)
        ->and(config('complaints.rate_limits.daily_cap'))->toBe(200)
        ->and(config('complaints.external_alternative.source'))->toContain('STPS');
});
