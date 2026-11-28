<?php

declare(strict_types=1);

use App\Modules\Attendance\Http\Middleware\ResolveDeviceTenant;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;

mutates(ResolveDeviceTenant::class);

beforeEach(function (): void {
    Route::middleware(['web', ResolveDeviceTenant::class])->get('a/{tenant:slug}/probe', fn (): string => resolve(CurrentTenant::class)->id().'|'.Context::get('tenant_id'));
});

it('works inside the company of the slug, with no account', function (): void {
    $tenant = Tenant::factory()->create();
    Tenant::factory()->create();

    $this->get("a/{$tenant->slug}/probe")->assertOk()->assertSeeText("{$tenant->id}|{$tenant->id}");
});

it('answers the same 404 for an unknown or a suspended company', function (Closure $slug): void {
    $this->get('a/'.$slug().'/probe')->assertNotFound();
})->with([
    'unknown' => [fn (): string => 'no-existe'],
    'deactivated' => [fn (): string => Tenant::factory()->suspended()->create()->slug],
    'expired' => [fn (): string => Tenant::factory()->activeUntil(now()->subDay())->create()->slug],
]);

it('answers 404 to a company without the registro de jornada and leaves its id out of the context', function (): void {
    $tenant = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::now(config()->string('app.display_timezone')));
    Context::forget('tenant_id');

    $this->get("a/{$tenant->slug}/probe")->assertNotFound();

    expect(Context::has('tenant_id'))->toBeFalse();
});
