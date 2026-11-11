<?php

declare(strict_types=1);

use App\Modules\Assistant\Actions\CheckAllowance;
use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Assistant\Support\TenantAiAccess;
use App\Modules\Tenancy\Models\Tenant;

mutates(TenantAiAccess::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
    config(['humi.monthly_token_budget' => 1000]);
});

it('is bound to the tenant implementation', function (): void {
    expect(resolve(AiAccess::class))->toBeInstanceOf(TenantAiAccess::class);
});

it('is available by default', function (): void {
    expect(resolve(AiAccess::class)->available())->toBeTrue();
});

it('is not available for a company whose Humi was turned off, and only for that one', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->b->id, 'enabled' => false]);
    expect(resolve(AiAccess::class)->available())->toBeTrue();

    TenantAiSettings::factory()->create(['tenant_id' => $this->a->id, 'enabled' => false]);
    expect(resolve(AiAccess::class)->available())->toBeFalse();
});

it('is not available once the budget of the month is used, and not one token before', function (): void {
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'input_tokens' => 999, 'output_tokens' => 0]);
    expect(resolve(AiAccess::class)->available())->toBeTrue();

    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'input_tokens' => 1, 'output_tokens' => 0]);
    expect(resolve(AiAccess::class)->available())->toBeFalse();
});

it('uses the budget set for the company instead of the default', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->a->id, 'monthly_token_budget' => 50]);
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'input_tokens' => 50, 'output_tokens' => 0]);

    expect(resolve(AiAccess::class)->available())->toBeFalse();
});

it('masks emails and phones before text leaves for the provider', function (): void {
    $text = resolve(AiAccess::class)->redact('Escriba a ana@empresa.mx o llame al 55 1234 5678.');

    expect($text)->toContain('[correo]', '[teléfono]')->not->toContain('ana@empresa.mx')->not->toContain('1234');
});

it('records a usage row with no person and no conversation, which counts against the month', function (): void {
    resolve(AiAccess::class)->record('CommentSummarizer', 300, 120, 1450);

    $row = AiUsage::query()->sole();

    expect($row->tenant_id)->toBe($this->a->id)
        ->and($row->participant_id)->toBeNull()
        ->and($row->conversation_id)->toBeNull()
        ->and($row->agent)->toBe('CommentSummarizer')
        ->and($row->input_tokens)->toBe(300)
        ->and($row->output_tokens)->toBe(120)
        ->and($row->latency_ms)->toBe(1450)
        ->and($row->tools)->toBe([])
        ->and($row->stopped)->toBeFalse();

    config(['humi.monthly_token_budget' => 420]);

    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->toThrow(AssistantUnavailable::class);
});
