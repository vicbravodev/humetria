<?php

declare(strict_types=1);

use Laravel\Ai\AiServiceProvider;

use function Laravel\Ai\agent;

it('has the Laravel AI SDK installed', function (): void {
    expect(class_exists(AiServiceProvider::class))->toBeTrue();
});

it('reads the provider and the models of Humi from config, Anthropic by default', function (): void {
    expect(config('humi.provider'))->toBe('anthropic')
        ->and(config('ai.default'))->toBe('anthropic')
        ->and(config('humi.model'))->toBeString()->not->toBeEmpty()
        ->and(config()->integer('humi.monthly_token_budget'))->toBeGreaterThan(0)
        ->and(config()->integer('humi.rate_per_minute'))->toBeGreaterThan(0)
        ->and(config()->integer('humi.rate_per_day'))->toBeGreaterThan(0)
        ->and(config()->integer('humi.tenant_rate_per_minute'))->toBeGreaterThan(0);
});

it('never reaches a provider from the tests: a prompt with no fake fails', function (): void {
    expect(fn () => agent(instructions: 'Test')->prompt('Hola'))->toThrow('Attempted request to');
});
