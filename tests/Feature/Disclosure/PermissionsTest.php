<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;

it('gives each role the disclosure permissions of the design', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-disclosure'))->toBe($view)
        ->and(Gate::allows('manage-disclosure'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
    'complaints handler' => [TenantRole::Complaints, false, false],
]);

it('reads its settings from config/disclosure.php', function (): void {
    expect(config('disclosure.link_max_months'))->toBe(36)
        ->and(config('disclosure.link_expiry_warning_days'))->toBe(30)
        ->and(config('disclosure.per_page'))->toBe(10)
        ->and(config('disclosure.history_per_page'))->toBe(5)
        ->and(config('disclosure.records_per_page'))->toBe(5)
        ->and(config('disclosure.workers_page.per_address_per_minute'))->toBe(60)
        ->and(config('disclosure.workers_page.per_link_per_minute'))->toBe(1200)
        ->and(config('disclosure.text_max'))->toBe(20000)
        ->and(config('disclosure.items_max'))->toBe(60);
});
