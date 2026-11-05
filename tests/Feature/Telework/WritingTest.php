<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\SaveWriting;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SaveWriting::class, Writer::class);

// Rediseño v2 (boards V2-Politica and V3-Doc-Riesgos, decisión A3): the NOM-037 documents are written by answering
// questions, one at a time, with the text beside them; every answer is kept, so the writing can be left and resumed.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @param array<string, mixed> $data */
function saveWriting(array $data, string $kind = 'policy'): Illuminate\Testing\TestResponse
{
    return test()->put(route('tenant.telework.writing.update', [test()->tenant, $kind]), $data);
}

it('opens the policy on its first question, with what the board marks and the text it makes so far', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->component('telework/write')
            ->where('document.kind', 'policy')
            ->where('document.title', 'Política de Teletrabajo')
            ->where('document.caption', 'NOM-037 · Etapa 1 · Política de Teletrabajo')
            ->where('document.owner', 'Autopartes Demo · todos los centros')
            ->where('minutes', 12)
            ->where('step', 0)
            ->has('questions', 8)
            ->where('questions.0.key', 'e')
            ->where('questions.0.topic', 'Horario y desconexión')
            ->where('questions.0.title', '¿En qué horario trabajan y desde cuándo nadie debe recibir mensajes de trabajo?')
            ->where('questions.0.fields.0.legend', 'Horario')
            ->where('questions.0.fields.0.options.0', ['value' => 'nine', 'label' => 'De 9:00 a 18:00'])
            ->where('questions.2.fields.0.multiple', true)
            ->where('answers.schedule', 'nine')
            ->where('answers.disconnect', 'end')
            ->where('answers.frequency', null)
            ->where('answers.channels', ['email', 'chat'])
            ->where('sections.0', ['key' => 'e', 'heading' => 'Horario y desconexión', 'text' => 'La jornada en teletrabajo es de 9:00 a 18:00, de lunes a viernes. Al terminar su horario, nadie está obligado a responder mensajes ni llamadas de trabajo.', 'base' => false, 'edited' => false])
            ->where('sections.1.text', null)
            ->has('items', 12)
            ->where('items.0', ['key' => 'a', 'label' => 'Cultura de prevención de riesgos de trabajo']));
});

it('keeps every answer and where the writing goes, and builds the text from them', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    saveWriting(['answers' => ['schedule' => 'eight', 'disconnect' => 'hour', 'frequency' => 'weekly', 'channels' => ['phone', 'email']], 'step' => 2])
        ->assertRedirect(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertSessionHasNoErrors();

    $draft = DocumentDraft::query()->sole();

    expect($draft->answers)->toMatchArray(['schedule' => 'eight', 'disconnect' => 'hour', 'frequency' => 'weekly', 'channels' => ['phone', 'email']])
        ->and($draft->step)->toBe(2)
        ->and($draft->updated_by)->toBe($this->hr->id);
    Log::shouldHaveReceived('info')->with('telework.writing_saved', ['kind' => 'policy', 'step' => 2])->once();

    $this->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('step', 2)
            ->where('answers.channels', ['phone', 'email'])
            ->where('sections.0.text', 'La jornada en teletrabajo es de 8:00 a 17:00, de lunes a viernes. A partir de una hora después de su horario, nadie está obligado a responder mensajes ni llamadas de trabajo.')
            ->where('sections.1.text', 'Para evitar el aislamiento, el equipo se reúne una vez por semana, por videollamada o en persona.')
            ->where('sections.2.text', 'La empresa se comunica con las personas en teletrabajo solo por correo de la empresa y teléfono, dentro de su horario y respetando su privacidad.'));
});

it('keeps answers given before when only the step changes, and one draft per document', function (): void {
    $this->actingAs($this->hr);
    saveWriting(['answers' => ['frequency' => 'daily']]);
    saveWriting(['step' => 3]);

    $draft = DocumentDraft::query()->sole();

    expect($draft->answers['frequency'])->toBe('daily')
        ->and($draft->step)->toBe(3);
});

it('leaves a section without text until its question is answered, and empty when every choice is taken off', function (): void {
    $this->actingAs($this->hr);
    saveWriting(['answers' => ['channels' => []]]);

    $this->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('answers.channels', [])->where('sections.2.text', null));
});

it('refuses an answer the questions do not have and keeps nothing', function (array $data): void {
    $this->actingAs($this->hr);

    saveWriting($data)->assertSessionHasErrors();

    expect(DocumentDraft::query()->exists())->toBeFalse();
})->with([
    'unknown field' => [['answers' => ['salary' => 'high']]],
    'unknown option' => [['answers' => ['schedule' => 'midnight']]],
    'several for a single choice' => [['answers' => ['schedule' => ['nine', 'eight']]]],
    'one for a multiple choice' => [['answers' => ['channels' => 'email']]],
    'unknown option in a list' => [['answers' => ['channels' => ['email', 'pigeon']]]],
    'repeated option' => [['answers' => ['channels' => ['email', 'email']]]],
    'step beyond the review' => [['step' => 10]],
    'negative step' => [['step' => -1]],
]);

it('lets only whoever manages NOM-037 write', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))->assertForbidden();
    $this->actingAs($user)->put(route('tenant.telework.writing.update', [$this->tenant, 'policy']), ['step' => 1])->assertForbidden();

    expect(DocumentDraft::query()->exists())->toBeFalse();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('answers 404 for a document that is not written with questions', function (string $kind): void {
    $this->actingAs($this->hr)->get(route('tenant.telework.writing.show', [$this->tenant, $kind]))->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.telework.writing.update', [$this->tenant, $kind]), ['step' => 1])->assertNotFound();
})->with(['inspection_report', 'nope']);

it('never shows the answers of another company', function (): void {
    $other = Tenant::factory()->create();
    DocumentDraft::factory()->create(['tenant_id' => $other->id, 'answers' => ['frequency' => 'daily'], 'step' => 4]);

    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('step', 0)->where('answers.frequency', null));
});

it('keeps one draft per company and document in the database too', function (): void {
    $draft = DocumentDraft::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => DB::transaction(fn () => DocumentDraft::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => $draft->kind])))
        ->toThrow(QueryException::class, 'telework_document_drafts_kind_unique');
});

it('keeps a section RH rewrote in place of the text the answers make, until it is put back', function (): void {
    $this->actingAs($this->hr);

    saveWriting(['section' => 'f', 'text' => "  Quien teletrabaja tiene los mismos derechos.\r\n\r\nY las mismas prestaciones.  "])->assertSessionHasNoErrors();

    expect(DocumentDraft::query()->sole()->edits)->toBe(['f' => "Quien teletrabaja tiene los mismos derechos.\n\nY las mismas prestaciones."]);
    $this->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('sections.9', ['key' => 'f', 'heading' => 'Mismos derechos', 'text' => "Quien teletrabaja tiene los mismos derechos.\n\nY las mismas prestaciones.", 'base' => true, 'edited' => true]));

    saveWriting(['section' => 'f', 'text' => null])->assertSessionHasNoErrors();

    expect(DocumentDraft::query()->sole()->edits)->toBe([]);
});

it('drops the rewritten text of a section when its question gets another answer, and only that one', function (): void {
    $this->actingAs($this->hr);
    saveWriting(['section' => 'b', 'text' => 'Nos vemos los lunes.']);
    saveWriting(['section' => 'e', 'text' => 'De 7 a 15.']);

    saveWriting(['answers' => ['frequency' => 'daily', 'schedule' => 'nine', 'disconnect' => 'end']]);

    expect(DocumentDraft::query()->sole()->edits)->toBe(['e' => 'De 7 a 15.']);
});

it('refuses to rewrite a section the document does not have, or too long a text', function (array $data): void {
    $this->actingAs($this->hr);

    saveWriting($data)->assertSessionHasErrors();

    expect(DocumentDraft::query()->exists())->toBeFalse();
})->with([
    'unknown section' => [['section' => 'z', 'text' => 'Algo']],
    'too long' => [['section' => 'f', 'text' => str_repeat('a', 5001)]],
]);
