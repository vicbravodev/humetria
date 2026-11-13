<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\SaveWriting;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\Writing\PolicyTemplate;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SaveWriting::class, PolicyTemplate::class);

// Rediseño v2 (board V2-N035-Politica, decisión A3): the policy of prevention is written by answering questions, one at a
// time, with the text beside it. It stays the example of Guía IV (criterio C-51): its commitments literal, the principles
// the company says are true in its centers, and the responsibles and resources the guide asks for.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @param array<string, mixed> $data */
function writePolicy(array $data): Illuminate\Testing\TestResponse
{
    return test()->put(route('tenant.disclosure.write.update', [test()->tenant, 'policy']), $data);
}

/** @return array<string, string|list<string>> Every question of the policy answered. */
function policyAnswers(): array
{
    return [
        'signature' => 'hr',
        'responsibles' => ['hr', 'supervisors'],
        'resources' => ['channel', 'training'],
        'practices' => ['a', 'h'],
        'principles' => ['p3', 'p8'],
        'dissemination' => ['page', 'induction'],
    ];
}

it('opens the policy on its first question, with the commitments of Guía IV as its base text', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.disclosure.write', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/policy-write')
            ->where('document.kind', 'policy')
            ->where('document.title', 'Política de prevención de riesgos psicosociales')
            ->where('document.caption', 'NOM-035 · Etapa 1 · Política de prevención')
            ->where('document.owner', 'Autopartes Demo · todos los centros')
            ->where('minutes', 10)
            ->where('step', 0)
            ->has('questions', 6)
            ->where('questions.0.key', 'signature')
            ->where('questions.0.topic', 'Compromiso')
            ->where('questions.0.title', '¿Quién firma la política en nombre de la empresa?')
            ->where('questions.0.fields.0.options.0', ['value' => 'direction', 'label' => 'La dirección general'])
            ->where('questions.3.fields.0.multiple', true)
            ->where('answers.signature', 'direction')
            ->where('answers.practices', [])
            ->where('sections.0.key', 'commitments')
            ->where('sections.0.base', true)
            ->where('sections.0.text', fn (string $text): bool => str_contains($text, 'En este centro de trabajo Autopartes Demo en relación con la prevención')
                && str_contains($text, "\n\n1. Es obligación de supervisores, gerentes y directores aplicar esta política y predicar con el ejemplo;")
                && str_contains($text, "\n\n9. Se crean espacios de participación y consulta"))
            ->where('sections.1', ['key' => 'signature', 'heading' => 'Quién la firma', 'text' => 'La firma la dirección general.', 'base' => false, 'edited' => false])
            ->where('sections.2.text', null)
            ->where('publication', ['draftId' => null, 'centersInUse' => 1, 'version' => null])
            ->where('back', ['href' => route('tenant.disclosure.policy', $this->tenant), 'label' => 'Guardar y volver', 'center' => null, 'centerName' => null]));
});

it('names the center in the preview of the policy when it is written from the route of one', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.disclosure.write', [$this->tenant, 'policy']).'?center='.$this->center->id)
        ->assertInertia(fn (Assert $page) => $page->where('document.owner', "Autopartes Demo · {$this->center->name}"));
});

it('goes back to the route of the center it came from', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.disclosure.write', [$this->tenant, 'policy']).'?center='.$this->center->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('back', ['href' => route('tenant.nom035.route', [$this->tenant, $this->center->id]), 'label' => 'Guardar y volver a la ruta', 'center' => $this->center->id, 'centerName' => $this->center->name]));
});

it('keeps each answer in the draft of the policy, with the text it makes', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    writePolicy(['answers' => policyAnswers(), 'step' => 6])->assertRedirect();

    $draft = Document::query()->sole();
    $text = array_column($draft->content, 'text', 'key');
    expect($draft->status)->toBe(DocumentStatus::Draft)
        ->and($draft->step)->toBe(6)
        ->and($draft->answers)->toEqual(policyAnswers())
        ->and(array_column($draft->content, 'key'))->toBe(['commitments', 'signature', 'responsibles', 'resources', 'practices', 'principles', 'dissemination'])
        ->and($text['commitments'])->toStartWith('En este centro de trabajo {legal_name} en relación')
        ->and($text['signature'])->toBe('La firma la dirección de Recursos Humanos.')
        ->and($text['responsibles'])->toBe('Son responsables de aplicar esta política: Recursos Humanos y los supervisores, gerentes y jefes de área. Reciben la capacitación para hacerlo.')
        ->and($text['resources'])->toBe('Para cumplir esta política, la empresa dispone de un canal confidencial de quejas y denuncias y capacitación para el personal.')
        ->and($text['practices'])->toBe("Para favorecer un entorno organizacional favorable, en el centro de trabajo se:\n\na) Realizan reuniones con los trabajadores para que todos tengan la oportunidad de exponer sus dudas, inquietudes, quejas relacionadas con su trabajo, y\n\nb) Capacita a todo el personal para el desempeño de sus funciones, su seguridad y desarrollo.")
        ->and($text['principles'])->toStartWith("La política se basa en los principios siguientes:\n\nEste centro de trabajo, para lograr la adecuada realización de las tareas encomendadas")
        ->and($text['principles'])->toContain("\n\nEste centro de trabajo se compromete a vigilar el cumplimiento")
        ->and($text['dissemination'])->toBe('Esta política se comparte en la página de las personas trabajadoras, donde cada persona confirma que la leyó y en la inducción de quien entra.');
    Log::shouldHaveReceived('info')->with('disclosure.writing_saved', ['kind' => 'policy', 'step' => 6]);
});

it('leaves a question not answered yet empty in the text, never with what it does not say', function (): void {
    $this->actingAs($this->hr);

    writePolicy(['answers' => ['signature' => 'owner'], 'step' => 1]);

    $text = array_column(Document::query()->sole()->content, 'text', 'key');
    expect($text['signature'])->toBe('La firma el dueño o la dueña de la empresa.')
        ->and($text['responsibles'])->toBe('')
        ->and($text['practices'])->toBe('');
});

it('refuses an answer the questions do not offer and a step that does not exist', function (array $data, string $error): void {
    $this->actingAs($this->hr);

    writePolicy($data)->assertSessionHasErrors($error);

    expect(Document::query()->count())->toBe(0);
})->with([
    'an option it does not offer' => [['answers' => ['signature' => 'board']], 'answers'],
    'a field it does not have' => [['answers' => ['schedule' => 'nine']], 'answers'],
    'a step after the review' => [['step' => 7], 'step'],
]);

it('rewrites a section on the review and drops the rewrite when its question gets another answer', function (): void {
    $this->actingAs($this->hr);
    writePolicy(['answers' => policyAnswers(), 'step' => 6]);

    writePolicy(['section' => 'resources', 'text' => 'Un presupuesto anual de prevención.']);
    expect(array_column(Document::query()->sole()->content, 'text', 'key')['resources'])->toBe('Un presupuesto anual de prevención.');

    writePolicy(['answers' => ['resources' => ['budget']]]);
    expect(array_column(Document::query()->sole()->content, 'text', 'key')['resources'])->toBe('Para cumplir esta política, la empresa dispone de un presupuesto para las acciones de prevención.')
        ->and(Document::query()->sole()->edits)->toBe([]);
});

it('publishes what the questions wrote as the next version, through the publication that exists', function (): void {
    $this->actingAs($this->hr);
    writePolicy(['answers' => policyAnswers(), 'step' => 6]);
    $draft = Document::query()->sole();

    $this->post(route('tenant.disclosure.policy.publish', [$this->tenant, $draft->id]).'?center='.$this->center->id, ['work_center_ids' => [$this->center->id], 'covers' => ['a', 'b', 'c'], 'reviewed' => '1'])
        ->assertRedirect(route('tenant.nom035.route', [$this->tenant, $this->center->id]));

    expect($draft->refresh()->status)->toBe(DocumentStatus::Published)
        ->and($draft->version)->toBe(1)
        ->and($draft->template_version)->toBe((new PolicyTemplate(resolve(App\Modules\Instruments\Contracts\Nom035Policy::class)))->version());
});

it('starts the next version from the answers of the one in force', function (): void {
    $this->actingAs($this->hr);
    writePolicy(['answers' => policyAnswers(), 'step' => 6]);
    $this->post(route('tenant.disclosure.policy.publish', [$this->tenant, Document::query()->sole()->id]), ['work_center_ids' => [$this->center->id], 'covers' => ['a', 'b', 'c'], 'reviewed' => '1']);

    $this->get(route('tenant.disclosure.write', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('answers.signature', 'hr')
            ->where('answers.practices', ['a', 'h'])
            ->where('publication.version.number', 1));

    writePolicy(['step' => 2]);
    expect(Document::query()->where('status', DocumentStatus::Draft)->sole()->answers)->toEqual(policyAnswers());
});

it('is written only by whoever manages the disclosure', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role));

    $this->get(route('tenant.disclosure.write', [$this->tenant, 'policy']))->assertForbidden();
    writePolicy(['answers' => ['signature' => 'hr']])->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('is a 404 for a kind that is not written', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.disclosure.write', [$this->tenant, 'results']))->assertNotFound();
});

it('keeps the draft of each company apart', function (): void {
    $other = Tenant::factory()->create(['slug' => 'other']);
    $this->actingAs($this->hr);
    writePolicy(['answers' => ['signature' => 'hr']]);

    $this->actingAs(memberOf($other, TenantRole::Hr))
        ->get(route('tenant.disclosure.write', [$other, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('answers.signature', 'direction')->where('publication.draftId', null));
});
