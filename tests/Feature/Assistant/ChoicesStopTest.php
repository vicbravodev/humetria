<?php

declare(strict_types=1);

use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Support\ChoicesOffered;
use App\Modules\Assistant\Tools\OfferChoices;
use App\Modules\Tenancy\Models\Tenant;
use Laravel\Ai\Middleware\Stop;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

mutates(ChoicesOffered::class);

const A_CARD = '{"render":"choices","title":"¿De qué parte?","rows":[{"label":"De todo","text":"Quiero todo"},{"label":"NOM-035","text":"Quiero NOM-035"}]}';

/** The step the model is about to take, after `$steps` were completed; each is one call of a tool with its result. */
function stepAfter(array $calls): PendingStep
{
    $steps = array_map(fn (array $call): Step => new Step(
        text: '',
        toolCalls: [new ToolCall('c-1', $call['tool'], [])],
        toolResults: [new ToolResult('c-1', $call['tool'], [], $call['result'], failed: $call['failed'] ?? false)],
        finishReason: FinishReason::ToolCalls,
        usage: new TextUsage,
        meta: new Meta,
        reasoning: '',
        replayBlocks: [],
    ), $calls);

    return new PendingStep(number: count($steps), isFinalStep: false, provider: 'anthropic', model: 'x', instructions: null, messages: [], tools: [], schema: null, options: null, steps: $steps);
}

it('is true right after a valid choices card', function (): void {
    expect(ChoicesOffered::in(stepAfter([['tool' => 'GetComplianceOverview', 'result' => '{}'], ['tool' => OfferChoices::NAME, 'result' => A_CARD]])))->toBeTrue();
});

it('is false after a note that is not a card, so the model can try again', function (): void {
    expect(ChoicesOffered::in(stepAfter([['tool' => OfferChoices::NAME, 'result' => 'No pude armar la pregunta: ofrece de 2 a 6 opciones distintas.']])))->toBeFalse()
        ->and(ChoicesOffered::in(stepAfter([['tool' => OfferChoices::NAME, 'result' => A_CARD, 'failed' => true]])))->toBeFalse();
});

it('is false after any other tool, and at the first step', function (): void {
    expect(ChoicesOffered::in(stepAfter([['tool' => 'ListPendingActions', 'result' => A_CARD]])))->toBeFalse()
        ->and(ChoicesOffered::in(stepAfter([])))->toBeFalse();
});

it('only looks at the last step, so a card offered earlier does not stop a later question', function (): void {
    expect(ChoicesOffered::in(stepAfter([['tool' => OfferChoices::NAME, 'result' => A_CARD], ['tool' => 'ListPendingActions', 'result' => '{}']])))->toBeFalse();
});

it('wires its second stop to the buttons: true right after a card, false before one', function (): void {
    $middleware = HumiAssistant::make(scope: assistantScopeIn(Tenant::factory()->create()))->middleware();
    $condition = (new ReflectionProperty(Stop::class, 'condition'))->getValue($middleware[1]);

    expect(value($condition, stepAfter([['tool' => OfferChoices::NAME, 'result' => A_CARD]])))->toBeTrue()
        ->and(value($condition, stepAfter([['tool' => 'GetComplianceOverview', 'result' => '{}']])))->toBeFalse();
});

it('is one of the two stops of Humi: the one of the person is the other', function (): void {
    $middleware = HumiAssistant::make(scope: assistantScopeIn(Tenant::factory()->create()))->middleware();

    expect($middleware)->toHaveCount(2)->each->toBeInstanceOf(Stop::class);
});
