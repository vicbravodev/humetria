<?php

declare(strict_types=1);

use App\Modules\Assistant\Actions\CheckAllowance;
use App\Modules\Assistant\Actions\RecordUsage;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

mutates(RecordUsage::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
});

it('records the tokens, the tools and the time of a message for the person and the company, never its text', function (): void {
    $usage = resolve(RecordUsage::class)->handle($this->scope, 'c0nv-1', 300, 120, ['GetRiskResults', 'SearchRegulations'], 1450, false);

    expect($usage->tenant_id)->toBe($this->a->id)
        ->and($usage->participant_id)->toBe($this->scope->participantId)
        ->and($usage->conversation_id)->toBe('c0nv-1')
        ->and($usage->agent)->toBe('HumiAssistant')
        ->and($usage->input_tokens)->toBe(300)
        ->and($usage->output_tokens)->toBe(120)
        ->and($usage->tools)->toBe(['GetRiskResults', 'SearchRegulations'])
        ->and($usage->latency_ms)->toBe(1450)
        ->and($usage->stopped)->toBeFalse()
        ->and($usage->created_at->toDateTimeString())->toBe('2026-11-18 18:00:00');
});

it('keeps no column that could hold what was said', function (): void {
    expect(Schema::getColumnListing('ai_usage'))->toBe(['id', 'tenant_id', 'participant_id', 'conversation_id', 'agent', 'input_tokens', 'output_tokens', 'tools', 'latency_ms', 'stopped', 'created_at']);
});

it('logs the message by its ids and counts, and a stopped one as stopped', function (): void {
    Log::spy();

    resolve(RecordUsage::class)->handle($this->scope, 'c0nv-1', 10, 5, ['GetRiskResults'], 800, false);
    resolve(RecordUsage::class)->handle($this->scope, 'c0nv-1', 4, 1, [], 200, true);

    Log::shouldHaveReceived('info')->with('assistant.message_sent', ['tenant_id' => $this->a->id, 'user_id' => $this->scope->userId, 'conversation_id' => 'c0nv-1', 'tokens' => 15, 'tools' => 1])->once();
    Log::shouldHaveReceived('info')->with('assistant.message_stopped', ['tenant_id' => $this->a->id, 'user_id' => $this->scope->userId, 'conversation_id' => 'c0nv-1', 'tokens' => 5, 'tools' => 0])->once();
});

it('counts for the budget of the company as soon as it is recorded, and for no other', function (): void {
    config(['humi.monthly_token_budget' => 500, 'humi.rate_per_minute' => 100, 'humi.rate_per_day' => 1000, 'humi.tenant_rate_per_minute' => 1000]);

    resolve(RecordUsage::class)->handle($this->scope, null, 300, 199, [], 500, false);
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->not->toThrow(AssistantUnavailable::class);

    resolve(RecordUsage::class)->handle($this->scope, null, 1, 0, [], 500, false);
    expect(fn () => resolve(CheckAllowance::class)->handle($this->scope))->toThrow(AssistantUnavailable::class);

    $inB = assistantScopeIn($this->b);
    expect(fn () => resolve(CheckAllowance::class)->handle($inB))->not->toThrow(AssistantUnavailable::class);
});

it('does not let one company read the usage of another', function (): void {
    resolve(RecordUsage::class)->handle($this->scope, null, 10, 5, [], 100, false);
    workIn($this->b);

    expect(AiUsage::query()->count())->toBe(0);
});
