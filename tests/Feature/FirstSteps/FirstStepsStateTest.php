<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->user = User::factory()->create();
});

it('keeps one state per company and user', function (): void {
    FirstStepsState::factory()->for($this->user)->create(['tenant_id' => $this->a->id]);

    expect(fn () => FirstStepsState::factory()->for($this->user)->create(['tenant_id' => $this->a->id]))
        ->toThrow(QueryException::class);
});

it('lets the same person have a state in each company', function (): void {
    FirstStepsState::factory()->for($this->user)->create(['tenant_id' => $this->a->id]);
    FirstStepsState::factory()->for($this->user)->create(['tenant_id' => $this->b->id]);

    expect(FirstStepsState::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('hides the state of company A from company B', function (): void {
    $state = FirstStepsState::factory()->welcomed()->create(['tenant_id' => $this->a->id]);
    workIn($this->b);

    expect(FirstStepsState::query()->count())->toBe(0)
        ->and(FirstStepsState::query()->find($state->id))->toBeNull()
        ->and(FirstStepsState::query()->whereKey($state->id)->update(['hidden_at' => now()]))->toBe(0);
});

it('has factory states for welcomed, hidden and completed', function (): void {
    workIn($this->a);

    expect(FirstStepsState::factory()->create()->welcomed_at)->toBeNull()
        ->and(FirstStepsState::factory()->welcomed()->create()->welcomed_at)->not->toBeNull()
        ->and(FirstStepsState::factory()->hidden()->create()->hidden_at)->not->toBeNull()
        ->and(FirstStepsState::factory()->completed()->create()->completed_at)->not->toBeNull();
});

it('has the switch off in the test environment and on by default', function (): void {
    expect(config('first_steps.enabled'))->toBeFalse()
        ->and((require base_path('config/first_steps.php'))['enabled'])->toBeBool();
});
