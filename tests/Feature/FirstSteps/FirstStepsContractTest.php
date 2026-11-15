<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\FirstSteps\Actions\CompleteFirstSteps;
use App\Modules\FirstSteps\Contracts\FirstSteps;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\FirstSteps\Support\CompanyFirstSteps;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(CompanyFirstSteps::class, CompleteFirstSteps::class);

beforeEach(function (): void {
    config(['first_steps.enabled' => true]);
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

function tourFor(Tenant $tenant, User $user): ?App\Modules\FirstSteps\Data\FirstStepsData
{
    workIn($tenant);

    return resolve(FirstSteps::class)->forUser($user->id);
}

/** A company where the only step left to do is the work center (no plan modules but one that is never done by this). */
function finishEverything(Tenant $tenant, User $owner): void
{
    // A plan with no evaluation tool and Humi off leave work centers, people and team.
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
    App\Modules\Assistant\Models\TenantAiSettings::factory()->create(['tenant_id' => $tenant->id, 'enabled' => false]);
    $center = WorkCenter::factory()->create(['tenant_id' => $tenant->id]);
    App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    memberOf($tenant, TenantRole::Hr);
}

it('is null while the switch is off', function (): void {
    config(['first_steps.enabled' => false]);

    expect(tourFor($this->tenant, $this->owner))->toBeNull();
});

it('gives the owner the steps with the welcome unseen and not hidden', function (): void {
    $tour = tourFor($this->tenant, $this->owner);

    expect($tour)->not->toBeNull()
        ->and($tour->steps)->not->toBeEmpty()
        ->and($tour->welcomed)->toBeFalse()
        ->and($tour->hidden)->toBeFalse();
});

it('is null for hr and viewer', function (TenantRole $role): void {
    expect(tourFor($this->tenant, memberOf($this->tenant, $role)))->toBeNull();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('is null for an owner who was made hr, even with a state row', function (): void {
    $former = memberOf($this->tenant, TenantRole::Hr);
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $former->id]);

    expect(tourFor($this->tenant, $former))->toBeNull();
});

it('is null and writes nothing for a read-only, a blocked or a union company', function (Closure $make): void {
    $tenant = $make();
    $owner = ownerOf($tenant);
    finishEverything($tenant, $owner);

    expect(tourFor($tenant, $owner))->toBeNull();
    $this->assertDatabaseCount('first_steps_states', 0);
})->with([
    'read only' => [fn () => Tenant::factory()->readOnly()->create()],
    'suspended' => [fn () => Tenant::factory()->suspended()->create()],
    'archived' => [fn () => Tenant::factory()->archived()->create()],
    'union' => [fn () => Tenant::factory()->union()->create()],
]);

it('carries the welcomed and hidden marks of the state row', function (): void {
    FirstStepsState::factory()->hidden()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $tour = tourFor($this->tenant, $this->owner);

    expect($tour?->welcomed)->toBeTrue()->and($tour->hidden)->toBeTrue();

    FirstStepsState::query()->withoutGlobalScopes()->delete();
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $tour = tourFor($this->tenant, $this->owner);

    expect($tour?->welcomed)->toBeTrue()->and($tour->hidden)->toBeFalse();
});

it('writes completed_at and is null when everything is done and the owner never saw the welcome', function (): void {
    finishEverything($this->tenant, $this->owner);

    expect(tourFor($this->tenant, $this->owner))->toBeNull();

    $state = FirstStepsState::query()->withoutGlobalScopes()->sole();
    expect($state->user_id)->toBe($this->owner->id)
        ->and($state->tenant_id)->toBe($this->tenant->id)
        ->and($state->completed_at)->not->toBeNull();
});

it('gives the finished steps once to an owner who was following them, then null', function (): void {
    finishEverything($this->tenant, $this->owner);
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $first = tourFor($this->tenant, $this->owner);

    expect($first)->not->toBeNull()
        ->and($first->allDone())->toBeTrue()
        ->and($first->welcomed)->toBeTrue()
        ->and($first->hidden)->toBeFalse()
        ->and(FirstStepsState::query()->withoutGlobalScopes()->sole()->completed_at)->not->toBeNull()
        ->and(tourFor($this->tenant, $this->owner))->toBeNull();
});

it('writes nothing while impersonated, and gives what it would have given', function (): void {
    finishEverything($this->tenant, $this->owner);
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    Log::spy();
    workIn($this->tenant);

    $tour = resolve(FirstSteps::class)->forUser($this->owner->id, impersonated: true);

    expect($tour?->allDone())->toBeTrue()
        ->and(FirstStepsState::query()->withoutGlobalScopes()->sole()->completed_at)->toBeNull();
    Log::shouldNotHaveReceived('info', ['first_steps.completed', Mockery::any()]);
});

it('is null while impersonated when everything is done and the owner had hidden them, still writing nothing', function (): void {
    finishEverything($this->tenant, $this->owner);
    FirstStepsState::factory()->hidden()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    workIn($this->tenant);

    expect(resolve(FirstSteps::class)->forUser($this->owner->id, impersonated: true))->toBeNull()
        ->and(FirstStepsState::query()->withoutGlobalScopes()->sole()->completed_at)->toBeNull();
});

it('is null at once when everything is done and the owner had hidden them', function (): void {
    finishEverything($this->tenant, $this->owner);
    FirstStepsState::factory()->hidden()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    expect(tourFor($this->tenant, $this->owner))->toBeNull()
        ->and(FirstStepsState::query()->withoutGlobalScopes()->sole()->completed_at)->not->toBeNull();
});

it('costs one query once completed', function (): void {
    FirstStepsState::factory()->completed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    workIn($this->tenant);
    $contract = resolve(FirstSteps::class);

    $this->expectsDatabaseQueryCount(1);
    expect($contract->forUser($this->owner->id))->toBeNull();
});

it('keeps each company of the same owner apart', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->owner)->create();
    FirstStepsState::factory()->hidden()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    expect(tourFor($this->tenant, $this->owner)?->hidden)->toBeTrue()
        ->and(tourFor($other, $this->owner)?->hidden)->toBeFalse()
        ->and(tourFor($other, $this->owner)?->welcomed)->toBeFalse();
});

it('logs first_steps.completed with the company and the user, once', function (): void {
    finishEverything($this->tenant, $this->owner);
    Log::spy();

    tourFor($this->tenant, $this->owner);
    tourFor($this->tenant, $this->owner);

    Log::shouldHaveReceived('info')
        ->with('first_steps.completed', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id])
        ->once();
});

it('completes again without duplicating the row', function (): void {
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    workIn($this->tenant);

    resolve(CompleteFirstSteps::class)->handle($this->tenant->id, $this->owner->id);
    resolve(CompleteFirstSteps::class)->handle($this->tenant->id, $this->owner->id);

    $state = FirstStepsState::query()->withoutGlobalScopes()->sole();
    expect($state->completed_at)->not->toBeNull()->and($state->welcomed_at)->not->toBeNull();
});
