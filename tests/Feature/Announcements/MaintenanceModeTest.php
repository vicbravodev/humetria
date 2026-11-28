<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Announcements\Actions\EndMaintenance;
use App\Modules\Announcements\Actions\StartMaintenance;
use App\Modules\Announcements\Contracts\PlatformNotices;
use App\Modules\Announcements\Models\MaintenancePeriod;
use App\Modules\Announcements\Support\LiveNotices;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(StartMaintenance::class, EndMaintenance::class, LiveNotices::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

/** @return array{reason: string, confirmation: string} */
function maintenanceForm(array $overrides = []): array
{
    return ['reason' => 'Migración de la base de datos', 'confirmation' => 'MANTENIMIENTO', ...$overrides];
}

function startMaintenance(): void
{
    test()->actingAs(superadmin())->post(route('announcements.maintenance.start'), maintenanceForm())->assertSessionHasNoErrors();
}

it('answers 404 to support and to everyone who is not the superadmin, before validating', function (Closure $who): void {
    $user = $who();

    $this->actingAs($user)->post(route('announcements.maintenance.start'), [])->assertNotFound()->assertSessionHasNoErrors();
    $this->actingAs($user)->delete(route('announcements.maintenance.end'))->assertNotFound();
    expect(MaintenancePeriod::query()->count())->toBe(0);
})->with([
    'support' => [fn (): User => support()],
    'a user' => [fn (): User => User::factory()->create()],
]);

it('starts the maintenance for a reason, with the word typed, and leaves it in the log and the trail', function (): void {
    Log::spy();
    startMaintenance();

    $period = MaintenancePeriod::query()->sole();
    expect($period->reason)->toBe('Migración de la base de datos')
        ->and($period->started_at->toIso8601String())->toBe('2026-11-26T15:00:00+00:00')
        ->and($period->ended_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'announcement.maintenance_started')->sole()->metadata)->toBe(['reason' => 'Migración de la base de datos'])
        ->and(resolve(PlatformNotices::class)->inMaintenance())->toBeTrue();
    Log::shouldHaveReceived('warning')->with('announcement.maintenance_started', ['maintenance_id' => $period->id]);
});

it('refuses to start it without a reason or without the word typed as asked', function (array $overrides, string $field): void {
    $this->actingAs(superadmin())->post(route('announcements.maintenance.start'), maintenanceForm($overrides))->assertSessionHasErrors($field);

    expect(MaintenancePeriod::query()->count())->toBe(0);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'a reason too long' => [['reason' => str_repeat('a', 501)], 'reason'],
    'no word' => [['confirmation' => ''], 'confirmation'],
    'another word' => [['confirmation' => 'mantenimiento'], 'confirmation'],
]);

it('does not start a second one while one runs', function (): void {
    startMaintenance();

    $this->post(route('announcements.maintenance.start'), maintenanceForm())->assertSessionHasErrors('reason');

    expect(MaintenancePeriod::query()->count())->toBe(1);
});

it('ends the maintenance and leaves it in the trail', function (): void {
    startMaintenance();
    $this->travel(2)->hours();

    $this->delete(route('announcements.maintenance.end'))->assertRedirect();

    expect(MaintenancePeriod::query()->sole()->ended_at?->toIso8601String())->toBe('2026-11-26T17:00:00+00:00')
        ->and(AuditEntry::query()->where('event', 'announcement.maintenance_ended')->count())->toBe(1)
        ->and(resolve(PlatformNotices::class)->inMaintenance())->toBeFalse();
});

it('shows the console whether the platform is in maintenance, since when and why', function (): void {
    $this->actingAs(superadmin())->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page
        ->where('maintenance', ['active' => false, 'reason' => null, 'since' => null]));

    startMaintenance();

    $this->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page
        ->where('maintenance', ['active' => true, 'reason' => 'Migración de la base de datos', 'since' => '26 nov 2026, 09:00']));
});

it('puts every company in read only while it lasts: its pages open, its changes get 423, its buttons go away', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = memberOf($tenant, TenantRole::Owner);
    startMaintenance();

    $this->actingAs($owner)->get(route('tenant.dashboard', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tenant.can.manage_organization', false)
        ->where('tenant.permissions.manage_organization', true)
        ->where('platform_notice.kind', 'maintenance')
        ->where('platform_notice.dismissible', false));
    $this->post(route('tenant.work-centers.store', $tenant), [])->assertStatus(423);

    // It is not the company's status: its portals, devices and surveys go on.
    expect($tenant->refresh()->status()->isBlocked())->toBeFalse();
});

it('gives every company its panel back when it ends', function (): void {
    $tenant = Tenant::factory()->create();
    startMaintenance();
    $this->delete(route('announcements.maintenance.end'));

    $this->actingAs(memberOf($tenant, TenantRole::Owner))->get(route('tenant.dashboard', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('tenant.can.manage_organization', true)
        ->missing('platform_notice'));
});

it('stops only the panel of the companies: no route outside /admin/{tenant} is held by the read-only guard', function (): void {
    $guarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => in_array(App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant::class, $route->gatherMiddleware(), true))
        ->reject(fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'admin/{tenant}'));

    expect($guarded->map->uri()->values()->all())->toBe([]);
});

it('comes back from the table within a minute when the cache was flushed', function (): void {
    startMaintenance();
    Cache::flush();

    expect(app(PlatformNotices::class)->inMaintenance())->toBeFalse();

    $this->artisan('announcements:refresh')->assertSuccessful();

    expect(app(PlatformNotices::class)->inMaintenance())->toBeTrue();
});
