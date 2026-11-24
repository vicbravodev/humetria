<?php

declare(strict_types=1);

use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Console\QualityAssistant;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Support\AnswerMetrics;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Laravel\Ai\Responses\Data\ToolCall;

mutates(QualityAssistant::class, AnswerMetrics::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->hr = memberOf($this->a, TenantRole::Hr);
});

function quality(array $options = []): Illuminate\Testing\PendingCommand
{
    return test()->artisan('humi:quality', ['tenant' => test()->a->id, '--as' => test()->hr->email, ...$options]);
}

it('asks Humi a fixed set of questions and reports, for each, its words, its links and whether it ends offering instead of saying the next step', function (): void {
    HumiAssistant::fake(fn (string $prompt): string => 'Vas bien. Revisa /admin/acme/campaigns/c1 para el detalle. Si quieres, te explico más.');

    quality()
        ->expectsOutputToContain('estado')
        ->expectsOutputToContain('12 palabras, 1 enlace, cierra con oferta')
        ->expectsOutputToContain('8 preguntas')
        ->expectsOutputToContain('Preguntaron con botones: 0 de 2 ambiguas; 0 de 6 claras.')
        ->assertSuccessful();
});

it('counts the answers that came as buttons, and the run ends right after them', function (): void {
    HumiAssistant::fake(fn (string $prompt): string|ToolCall => str_contains($prompt, '¿Qué me falta') ? new ToolCall('c-1', 'OfferChoices', [
        'question' => '¿De qué parte?',
        'options' => [['label' => 'De todo', 'text' => 'Quiero todo'], ['label' => 'NOM-035', 'text' => 'Quiero NOM-035']],
    ]) : 'Listo.');

    quality()
        ->expectsOutputToContain('pendientes: 0 palabras, 0 enlaces, pregunta con botones')
        ->expectsOutputToContain('Preguntaron con botones: 1 de 2 ambiguas; 0 de 6 claras.')
        ->assertSuccessful();
});

it('sees the buttons in the tools an answer called', function (): void {
    expect(AnswerMetrics::of('x', ['OfferChoices'])->asksChoices)->toBeTrue()
        ->and(AnswerMetrics::of('x', ['GetComplianceOverview'])->asksChoices)->toBeFalse()
        ->and(AnswerMetrics::of('x')->asksChoices)->toBeFalse();
});

it('averages the words and counts the answers that end with an offer, so two prompts or two models can be compared by number', function (): void {
    HumiAssistant::fake(fn (string $prompt): string => 'Respuesta corta sin oferta.');

    quality()
        ->expectsOutputToContain('Promedio: 4 palabras')
        ->expectsOutputToContain('0 de 8 cierran con oferta')
        ->assertSuccessful();
});

it('can try another model without touching the configuration of the app', function (): void {
    $modelsUsed = [];
    HumiAssistant::fake(function (string $prompt) use (&$modelsUsed): string {
        $modelsUsed[] = config('humi.model');

        return 'Listo.';
    });
    $before = config('humi.model');

    quality(['--model' => 'claude-haiku-5-5'])->assertSuccessful();

    expect(array_unique($modelsUsed))->toBe(['claude-haiku-5-5'])
        ->and(config('humi.model'))->toBe($before);
});

it('reports a provider that fails as a failed question, not as a crash, and stores nothing', function (): void {
    HumiAssistant::fake(fn (string $prompt): string => throw new RuntimeException('provider down'));

    quality()->expectsOutputToContain('el modelo falló')->assertFailed();

    expect(AiUsage::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('does not run in production, and refuses a person who is not of that company', function (): void {
    quality(['--as' => 'nadie@otra.mx'])->expectsOutputToContain('no es de esa empresa')->assertFailed();

    app()->detectEnvironment(fn (): string => 'production');

    quality()->expectsOutputToContain('No corre en producción')->assertFailed();
});
