<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

mutates(ForbidWhileImpersonating::class);

/** A passkey of the owner, so the route that binds one finds it: the only parameter any of these routes takes. */
function passkeyOf(User $owner): int
{
    return DB::table('passkeys')->insertGetId([
        'user_id' => $owner->id,
        'name' => 'Laptop',
        'credential_id' => 'credential-'.$owner->id,
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Every account route of the owner: [method, route name, takes a passkey]. */
dataset('owner account routes', [
    'profile.edit' => ['get', 'profile.edit', false],
    'profile.update' => ['patch', 'profile.update', false],
    'profile.destroy' => ['delete', 'profile.destroy', false],
    'security.edit' => ['get', 'security.edit', false],
    'user-password.update' => ['put', 'user-password.update', false],
    'password.confirm' => ['get', 'password.confirm', false],
    'password.confirm.store' => ['post', 'password.confirm.store', false],
    'password.confirmation' => ['get', 'password.confirmation', false],
    'two-factor.confirm' => ['post', 'two-factor.confirm', false],
    'two-factor.enable' => ['post', 'two-factor.enable', false],
    'two-factor.disable' => ['delete', 'two-factor.disable', false],
    'two-factor.qr-code' => ['get', 'two-factor.qr-code', false],
    'two-factor.recovery-codes' => ['get', 'two-factor.recovery-codes', false],
    'two-factor.regenerate-recovery-codes' => ['post', 'two-factor.regenerate-recovery-codes', false],
    'two-factor.secret-key' => ['get', 'two-factor.secret-key', false],
    'passkey.store' => ['post', 'passkey.store', false],
    'passkey.registration-options' => ['get', 'passkey.registration-options', false],
    'passkey.destroy' => ['delete', 'passkey.destroy', true],
]);

it('hides the owner\'s own account while impersonating', function (string $method, string $name, bool $takesPasskey): void {
    $tenant = Tenant::factory()->create();
    $passkey = passkeyOf(ownerOf($tenant));
    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant));

    $this->{$method}(route($name, $takesPasskey ? ['passkey' => $passkey] : []))->assertNotFound();
})->with('owner account routes');

it('leaves the owner\'s own account reachable when not impersonating', function (string $method, string $name, bool $takesPasskey): void {
    $owner = ownerOf(Tenant::factory()->create());
    $passkey = passkeyOf($owner);
    $this->actingAs($owner);

    expect($this->{$method}(route($name, $takesPasskey ? ['passkey' => $passkey] : []))->getStatusCode())->not->toBe(404);
})->with('owner account routes');

it('keeps the appearance settings while impersonating', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant));

    $this->get(route('appearance.edit'))->assertOk();
});

it('guards every account route Fortify registers under admin/user', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/user/'))
        ->map(fn ($route) => $route->getName());

    expect($names)->not->toBeEmpty()
        ->and($names->diff(ForbidWhileImpersonating::protectedRoutes())->values()->all())->toBe([]);
});

it('guards every route under admin/settings except settings and appearance', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/settings'))
        ->map(fn ($route) => $route->getName());

    expect($names)->not->toBeEmpty()
        ->and($names->diff(ForbidWhileImpersonating::protectedRoutes())->diff(['settings', 'appearance.edit'])->values()->all())->toBe([]);
});

it('sends settings to profile, or to appearance while impersonating', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($owner)->get(route('settings'))->assertRedirect(route('profile.edit'));

    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant));
    $this->get(route('settings'))->assertRedirect(route('appearance.edit'));
});
