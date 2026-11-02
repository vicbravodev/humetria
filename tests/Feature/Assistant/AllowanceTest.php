<?php

declare(strict_types=1);

use App\Modules\Assistant\Actions\CheckAllowance;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Tenancy\Models\Tenant;

mutates(CheckAllowance::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
    config(['humi.monthly_token_budget' => 1000, 'humi.rate_per_minute' => 100, 'humi.rate_per_day' => 1000, 'humi.tenant_rate_per_minute' => 1000]);
});

function usedTokens(Tenant $tenant, int $tokens, string $at = 'now'): void
{
    AiUsage::factory()->create(['tenant_id' => $tenant->id, 'input_tokens' => $tokens, 'output_tokens' => 0, 'created_at' => $at]);
}

function refusal(callable $attempt): AssistantUnavailable
{
    try {
        $attempt();
    } catch (AssistantUnavailable $refused) {
        return $refused;
    }

    throw new RuntimeException('Humi answered when it should have refused.');
}

it('lets a company that never configured Humi use it', function (): void {
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);
});

it('refuses a company whose Humi was turned off, with a message that says who to ask', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->a->id, 'enabled' => false]);

    $refused = refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope));

    expect($refused->reason)->toBe(AssistantUnavailable::DISABLED)
        ->and($refused->getMessage())->toBe('Humi no está disponible para tu empresa. Pídele a tu administrador que la active.');
});

it('turns off only the company that was turned off', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->b->id, 'enabled' => false]);

    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);
});

it('refuses at the budget of the month and not one token before', function (): void {
    usedTokens($this->a, 999);
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);

    usedTokens($this->a, 1);
    $refused = refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope));

    expect($refused->reason)->toBe(AssistantUnavailable::BUDGET)
        ->and($refused->getMessage())->toBe('Tu empresa agotó el uso de Humi de este mes. Se renueva el primer día del mes.');
});

it('counts the month in Mexico City time, nothing of the last one and nothing of another company', function (): void {
    usedTokens($this->a, 5000, '2026-10-31 23:00:00');
    usedTokens($this->b, 5000);

    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);

    // 2026-11-01 03:00 UTC is still 31 Oct 21:00 in Mexico City: October's.
    usedTokens($this->a, 5000, '2026-11-01 03:00:00');
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);

    usedTokens($this->a, 1000, '2026-11-01 07:00:00');
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->toThrow(AssistantUnavailable::class);
});

it('uses the budget the superadmin set for the company instead of the default', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->a->id, 'monthly_token_budget' => 50]);
    usedTokens($this->a, 50);

    expect(refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope))->reason)->toBe(AssistantUnavailable::BUDGET);
});

it('limits the messages of one person per minute, and says when to come back', function (): void {
    config(['humi.rate_per_minute' => 2]);
    resolve(CheckAllowance::class)->handle($this->scope);
    resolve(CheckAllowance::class)->handle($this->scope);

    $refused = refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope));

    expect($refused->reason)->toBe(AssistantUnavailable::RATE)
        ->and($refused->retryAfter)->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($refused->getMessage())->toBe('Alcanzaste el límite de mensajes por ahora. Puedes seguir en unos minutos.');

    $this->travel(61)->seconds();

    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);
});

it('limits the messages of one person per day', function (): void {
    config(['humi.rate_per_day' => 3]);

    foreach (range(1, 3) as $ignored) {
        resolve(CheckAllowance::class)->handle($this->scope);
        $this->travel(2)->minutes();
    }

    expect(refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope))->reason)->toBe(AssistantUnavailable::RATE);
});

it('does not let the limit of one person stop another of the same company', function (): void {
    config(['humi.rate_per_minute' => 1]);
    $other = assistantScopeIn($this->a);
    resolve(CheckAllowance::class)->handle($this->scope);

    expect(fn () => resolve(CheckAllowance::class)->handle($other))->not->toThrow(AssistantUnavailable::class);
});

it('limits the messages of the whole company per minute', function (): void {
    config(['humi.tenant_rate_per_minute' => 2]);
    $second = assistantScopeIn($this->a);
    resolve(CheckAllowance::class)->handle($this->scope);
    resolve(CheckAllowance::class)->handle($second);

    expect(refusal(fn () => resolve(CheckAllowance::class)->handle($this->scope))->reason)->toBe(AssistantUnavailable::RATE);
});

it('does not spend the limit of a message it refuses', function (): void {
    config(['humi.rate_per_minute' => 1]);
    TenantAiSettings::factory()->create(['tenant_id' => $this->a->id, 'enabled' => false]);
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->toThrow(AssistantUnavailable::class);

    TenantAiSettings::query()->update(['enabled' => true]);

    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);
});

it('does not count companies together: the limit of one is not the limit of another', function (): void {
    config(['humi.tenant_rate_per_minute' => 1]);
    resolve(CheckAllowance::class)->handle($this->scope);
    $inB = assistantScopeIn($this->b);

    expect(fn () => resolve(CheckAllowance::class)->handle($inB))->not->toThrow(AssistantUnavailable::class);
});
