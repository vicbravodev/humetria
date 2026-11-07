<?php

declare(strict_types=1);

use App\Modules\Attendance\Http\Middleware\ResolveDeviceTenant;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
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
    'deactivated' => [fn (): string => Tenant::factory()->deactivated()->create()->slug],
    'expired' => [fn (): string => Tenant::factory()->activeUntil(now()->subDay())->create()->slug],
]);
