<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\ContentPages;
use App\Modules\Disclosure\Support\Writing\ComplaintTemplate;
use App\Modules\Disclosure\Support\Writing\HealthTemplate;
use App\Modules\Disclosure\Support\Writing\PreventionTemplate;
use App\Modules\Disclosure\Support\Writing\Templates;
use App\Modules\Disclosure\Support\Writing\ViolenceTemplate;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Support\Writing\Field;
use App\Support\Writing\Writer;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ViolenceTemplate::class, PreventionTemplate::class, ComplaintTemplate::class, HealthTemplate::class, Templates::class, PublishContent::class, ContentPages::class);

// Rediseño v2 (board V4-Difusion-Contenidos, decisión A3): the blocks of 5.7 the company tells its people (b, c, d and f) are
// written by answering questions, with how the people will read it on their phone beside them. The health effects have no
// question: Humetría's draft, to review with someone with medical criteria before publishing (criterio C-52).

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @return array<string, string|list<string>> The first option of every field of the kind's questions. */
function firstContentOptions(DocumentKind $kind): array
{
    $answers = [];

    foreach (resolve(Templates::class)->template($kind)?->questions() ?? [] as $question) {
        foreach ($question->fields as $field) {
            $first = array_key_first($field->options);
            $answers[$field->key] = $field->multiple ? [$first] : $first;
        }
    }

    return $answers;
}

it('opens the measures against violence on their first question, as the board marks them', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.disclosure.write', [$this->tenant, 'violence_measures']))
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/content-write')
            ->where('document.kind', 'violence_measures')
            ->where('document.inciso', 'b')
            ->where('document.title', 'Medidas contra la violencia laboral')
            ->where('document.company', 'Autopartes Demo')
            ->has('questions', 4)
            ->where('questions.0.title', '¿Qué consideran violencia laboral?')
            ->where('questions.1.title', '¿Qué pasa cuando alguien la denuncia?')
            ->where('answers.considered', ['shouting', 'humiliation', 'harassment'])
            ->where('sections.0', ['key' => 'considered', 'heading' => 'Qué consideramos violencia', 'text' => 'Gritos, insultos o amenazas, humillaciones o burlas y acoso u hostigamiento.', 'base' => false, 'edited' => false])
            ->where('sections.1.heading', 'Si usted la denuncia')
            ->where('sections.1.text', 'Le confirmamos en 3 días que la recibimos y le respondemos en 30 días qué hicimos.')
            ->where('back.href', route('tenant.disclosure.index', $this->tenant)));
});

it('opens the health effects on their review: a draft of Humetría to check, with no question', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.disclosure.write', [$this->tenant, 'health_effects']))
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/content-write')
            ->where('document.inciso', 'f')
            ->has('questions', 0)
            ->where('sections.0.base', true)
            ->where('sections.0.text', fn (string $text): bool => str_starts_with($text, 'Los factores de riesgo psicosocial son aquellos que pueden provocar trastornos de ansiedad')));
});

it('writes every section of a block once its questions are answered', function (DocumentKind $kind): void {
    $template = resolve(Templates::class)->template($kind) ?? throw new LogicException;
    $writer = resolve(Writer::class);

    expect($writer->complete($template, firstContentOptions($kind)))->toBeTrue()
        ->and(array_filter($writer->sections($template, firstContentOptions($kind)), fn (array $section): bool => $section['text'] === null))->toBe([])
        ->and(array_map(fn (Field $field): bool => $field->options !== [], array_merge([], ...array_map(fn ($question): array => $question->fields, $template->questions()))))->not->toContain(false);
})->with([DocumentKind::ViolenceMeasures, DocumentKind::PreventionMeasures, DocumentKind::ComplaintMechanism, DocumentKind::HealthEffects]);

// C-20: «Nadie recibirá represalias» is a promise the company has not made in the app, and the channel it points to may not be anonymous.
it('does not promise what the company has not: no retaliation in the text of the complaint block', function (): void {
    $template = resolve(Templates::class)->template(DocumentKind::ComplaintMechanism) ?? throw new LogicException;
    $text = resolve(Writer::class)->sections($template, ['channels' => ['channel', 'hr']])[0]['text'];

    expect($text)->toBe('Puede presentar una queja o denuncia por el canal de quejas, también sin dar su nombre y con Recursos Humanos, en persona o por correo.')
        ->not->toContain('represalias');
});

it('keeps the answers in the draft of the block and publishes it to the centers, back to the disclosure', function (): void {
    $this->actingAs($this->hr);

    $this->put(route('tenant.disclosure.write.update', [$this->tenant, 'prevention_measures']), ['answers' => ['training' => 'yearly', 'workload' => 'quarter', 'actions' => ['breaks']], 'step' => 3])->assertRedirect();

    $draft = Document::query()->sole();
    expect($draft->kind)->toBe(DocumentKind::PreventionMeasures)
        ->and(array_column($draft->content, 'text', 'key'))->toBe([
            'training' => 'Capacitamos a todo el personal sobre los riesgos psicosociales una vez al año.',
            'workload' => 'Revisamos las cargas de trabajo cada trimestre.',
            'actions' => 'También hay pausas acordadas por turno.',
        ]);

    $this->post(route('tenant.disclosure.contents.publish', [$this->tenant, 'prevention_measures', $draft->id]).'?back=board', ['all_centers' => true])
        ->assertRedirect(route('tenant.disclosure.index', $this->tenant));

    expect($draft->refresh()->status)->toBe(DocumentStatus::Published)
        ->and(ContentPages::text($draft))->toBe("Capacitación\nCapacitamos a todo el personal sobre los riesgos psicosociales una vez al año.\n\nCargas de trabajo\nRevisamos las cargas de trabajo cada trimestre.\n\nOtras medidas\nTambién hay pausas acordadas por turno.");
});

it('refuses to publish a block whose questions wrote nothing', function (): void {
    $this->actingAs($this->hr);
    $this->put(route('tenant.disclosure.write.update', [$this->tenant, 'complaint_mechanism']), ['answers' => ['channels' => []], 'step' => 1]);

    $this->post(route('tenant.disclosure.contents.publish', [$this->tenant, 'complaint_mechanism', Document::query()->sole()->id]), ['all_centers' => true])
        ->assertSessionHasErrors('body');
});
