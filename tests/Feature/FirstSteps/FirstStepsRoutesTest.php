<?php

declare(strict_types=1);

use App\Modules\FirstSteps\Actions\CompleteFirstSteps;
use App\Modules\FirstSteps\Actions\HideFirstSteps;
use App\Modules\FirstSteps\Actions\MarkWelcomed;
use App\Modules\FirstSteps\Actions\ShowFirstSteps;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\FirstSteps\Policies\FirstStepsStatePolicy;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(CompleteFirstSteps::class, MarkWelcomed::class, HideFirstSteps::class, ShowFirstSteps::class, FirstStepsStatePolicy::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

function stateOf(Tenant $tenant, int $userId): ?FirstStepsState
{
    return FirstStepsState::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('user_id', $userId)->first();
}

it('lives at /admin/{slug}/first-steps/*', function (): void {
    expect(route('tenant.first-steps.welcome', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/first-steps/welcome')
        ->and(route('tenant.first-steps.hide', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/first-steps/hide')
        ->and(route('tenant.first-steps.show', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/first-steps/show');
});

it('marks the welcome as seen for the owner in that company only', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->owner)->create();

    $this->actingAs($this->owner)->post(route('tenant.first-steps.welcome', $this->tenant))->assertRedirect();

    expect(stateOf($this->tenant, $this->owner->id)?->welcomed_at)->not->toBeNull()
        ->and(stateOf($other, $this->owner->id))->toBeNull();
});

it('keeps the first welcomed_at and one row when it is sent twice', function (): void {
    Log::spy();
    $this->actingAs($this->owner)->post(route('tenant.first-steps.welcome', $this->tenant));
    $first = stateOf($this->tenant, $this->owner->id)?->welcomed_at;
    $this->travel(5)->minutes();

    $this->actingAs($this->owner)->post(route('tenant.first-steps.welcome', $this->tenant));

    expect(FirstStepsState::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(stateOf($this->tenant, $this->owner->id)?->welcomed_at?->equalTo($first))->toBeTrue();
    Log::shouldHaveReceived('info')->with('first_steps.welcomed', Mockery::any())->once();
});

it('hides and shows again', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.first-steps.hide', $this->tenant))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Puedes retomarlos desde tu menú de usuario.']);
    expect(stateOf($this->tenant, $this->owner->id)?->hidden_at)->not->toBeNull();

    $this->actingAs($this->owner)->post(route('tenant.first-steps.show', $this->tenant))->assertRedirect();
    expect(stateOf($this->tenant, $this->owner->id)?->hidden_at)->toBeNull();
});

it('does not write or log when there is nothing to change', function (): void {
    FirstStepsState::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    Log::spy();

    $this->actingAs($this->owner)->post(route('tenant.first-steps.show', $this->tenant));

    Log::shouldNotHaveReceived('info');
});

it('answers 403 to hr and viewer', function (string $route, TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->post(route($route, $this->tenant))->assertForbidden();

    expect(FirstStepsState::query()->withoutGlobalScopes()->count())->toBe(0);
})->with(['tenant.first-steps.welcome', 'tenant.first-steps.hide', 'tenant.first-steps.show'])
    ->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 for a company the person does not belong to', function (): void {
    $stranger = ownerOf(Tenant::factory()->create());

    $this->actingAs($stranger)->post(route('tenant.first-steps.hide', $this->tenant))->assertNotFound();
});

it('sends a guest to the login', function (): void {
    $this->post(route('tenant.first-steps.welcome', $this->tenant))->assertRedirect(route('login'));
});

it('logs first_steps.welcomed, first_steps.hidden and first_steps.shown with tenant_id and user_id', function (): void {
    Log::spy();
    $context = ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id];

    $this->actingAs($this->owner)->post(route('tenant.first-steps.welcome', $this->tenant));
    $this->actingAs($this->owner)->post(route('tenant.first-steps.hide', $this->tenant));
    $this->actingAs($this->owner)->post(route('tenant.first-steps.show', $this->tenant));

    Log::shouldHaveReceived('info')->with('first_steps.welcomed', $context)->once();
    Log::shouldHaveReceived('info')->with('first_steps.hidden', $context)->once();
    Log::shouldHaveReceived('info')->with('first_steps.shown', $context)->once();
});

it('refuses the three posts while a superadmin works as the owner', function (string $name): void {
    $this->actingAs(superadmin())->post(route('companies.impersonate', $this->tenant));

    $this->post(route($name, $this->tenant))->assertNotFound();

    expect(stateOf($this->tenant, $this->owner->id))->toBeNull();
})->with(['tenant.first-steps.welcome', 'tenant.first-steps.hide', 'tenant.first-steps.show']);

/**
 * Two first writes at once must not trip the unique (tenant_id, user_id) index. A faithful race cannot be staged here: the
 * test connection lives in one transaction, so a racing row would be rolled back with the savepoint that loses. What is pinned
 * is the form that survives it, insert first and read the winner after, never look first and insert later.
 */
it('inserts before it looks, so a concurrent first write finds the winner instead of failing', function (string $action): void {
    workIn($this->tenant);
    DB::enableQueryLog();

    resolve($action)->handle($this->tenant->id, $this->owner->id);

    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, '"first_steps_states"'));
    expect($queries->first())->toStartWith('insert into');
})->with([MarkWelcomed::class, HideFirstSteps::class, ShowFirstSteps::class, CompleteFirstSteps::class]);

it('applies the change to a row that is already there, once, keeping the first welcomed_at', function (): void {
    workIn($this->tenant);
    Log::spy();
    $first = now()->subDay()->startOfSecond();
    FirstStepsState::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'welcomed_at' => $first]);

    resolve(MarkWelcomed::class)->handle($this->tenant->id, $this->owner->id);
    resolve(HideFirstSteps::class)->handle($this->tenant->id, $this->owner->id);
    resolve(CompleteFirstSteps::class)->handle($this->tenant->id, $this->owner->id);

    $state = stateOf($this->tenant, $this->owner->id);
    expect(FirstStepsState::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($state?->welcomed_at?->equalTo($first))->toBeTrue()
        ->and($state?->hidden_at)->not->toBeNull()
        ->and($state?->completed_at)->not->toBeNull();
    Log::shouldNotHaveReceived('info', ['first_steps.welcomed', Mockery::any()]);
});
