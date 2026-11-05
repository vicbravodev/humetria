<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\Writing\Field;
use App\Modules\Telework\Support\Writing\Question;
use App\Modules\Telework\Support\Writing\Section;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Writer::class);

// Rediseño v2 (boards V2-N037-Docs and V3-Doc-Riesgos; docs/02, «Documentos con preguntas»): the six supporting documents
// are written with the same engine as the policy, each with its three or four questions.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** Every field answered with its first option, or the first one in a list. @return array<string, string|list<string>> */
function firstOptions(DocumentKind $kind): array
{
    $answers = [];

    foreach (resolve(Writer::class)->template($kind)?->questions() ?? [] as $question) {
        foreach ($question->fields as $field) {
            $first = array_key_first($field->options);
            $answers[$field->key] = $field->multiple ? [$first] : $first;
        }
    }

    return $answers;
}

it('writes each supporting document with its questions, its minutes and the incisos it covers', function (DocumentKind $kind, string $name, string $title, int $questions, int $minutes, int $items): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, $kind->value]))
        ->assertInertia(fn (Assert $page) => $page->component('telework/write')
            ->where('document.caption', "NOM-037 · Documentos de apoyo · {$name}")
            ->where('document.title', $title)
            ->where('document.owner', 'Autopartes Demo · todos los centros')
            ->where('minutes', $minutes)
            ->has('questions', $questions)
            ->has('items', $items));
})->with([
    '5.3' => [DocumentKind::RiskInformation, 'Riesgos de trabajar desde casa', 'Riesgos de trabajar desde casa y cómo prevenirlos', 4, 5, 0],
    '5.6' => [DocumentKind::ImplementationProcess, 'Cómo se empieza a teletrabajar', 'Cómo se empieza a teletrabajar', 4, 5, 4],
    '5.8' => [DocumentKind::MaintenanceProgram, 'Mantenimiento del equipo', 'Programa de mantenimiento del equipo', 4, 4, 0],
    '5.10' => [DocumentKind::Reversibility, 'Regreso a la oficina', 'Regreso al trabajo presencial', 4, 4, 0],
    '5.13' => [DocumentKind::FamilyViolence, 'Apoyo si alguien vive violencia en casa', 'Atención de casos de violencia familiar', 4, 7, 0],
    '5.14' => [DocumentKind::CommitteeParticipation, 'Participación en las comisiones', 'Participación en las comisiones', 3, 3, 0],
]);

it('asks for the risks as board V3-Doc-Riesgos does, with the three most common marked', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, 'risk_information']))
        ->assertInertia(fn (Assert $page) => $page->where('questions.1.topic', 'Riesgos')
            ->where('questions.1.title', '¿Qué riesgos quieres explicarle a tu equipo?')
            ->where('questions.1.hint', 'Marcamos los más comunes en trabajo de oficina desde casa. Quita o agrega según lo que hacen.')
            ->has('questions.1.fields.0.options', 5)
            ->where('answers.risks', ['posture', 'electric', 'isolation'])
            ->where('sections.1', ['key' => 'risks', 'heading' => 'Los riesgos y qué hacer', 'text' => "Postura y pantalla. Use una silla con respaldo, la pantalla a la altura de los ojos y haga una pausa breve cada hora.\n\nInstalación eléctrica. No conecte varios aparatos a una sola extensión; avise si un contacto está dañado.\n\nAislamiento. Participe en las reuniones del equipo y avise si se siente desconectado.", 'base' => false, 'edited' => false]));
});

it('gives every question a section of its own and writes them all once each is answered', function (DocumentKind $kind): void {
    $writer = resolve(Writer::class);
    $template = $writer->template($kind);
    $sections = $writer->sections($template, firstOptions($kind));
    $keys = array_column($sections, 'key');
    $fields = array_merge(...array_map(fn (Question $question): array => array_map(fn (Field $field): string => $field->key, $question->fields), $template->questions()));

    expect($writer->complete($template, firstOptions($kind)))->toBeTrue()
        ->and($writer->complete($template, []))->toBeFalse()
        ->and(array_column($sections, 'text'))->not->toContain(null)
        ->and($keys)->toBe(array_values(array_unique($keys)))
        ->and(array_map(fn (Question $question): string => $question->key, $template->questions()))->each->toBeIn($keys)
        ->and($fields)->toBe(array_values(array_unique($fields)))
        ->and(array_diff($kind->coverableItems(), $keys))->toBe([])
        ->and(array_filter($template->sections(new App\Modules\Telework\Support\Writing\Answers([])), fn (Section $section): bool => ! $section->base && $section->text !== null))->toBe([]);
})->with(array_filter(DocumentKind::cases(), fn (DocumentKind $kind): bool => $kind !== DocumentKind::InspectionReport));

it('publishes a supporting document with the incisos its kind covers, and the 10.3 row counts it', function (): void {
    App\Modules\Telework\Models\TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    DocumentDraft::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => DocumentKind::ImplementationProcess, 'answers' => firstOptions(DocumentKind::ImplementationProcess), 'step' => 4]);
    DocumentDraft::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => DocumentKind::FamilyViolence, 'answers' => firstOptions(DocumentKind::FamilyViolence), 'step' => 4]);

    $this->actingAs($this->hr);
    $this->post(route('tenant.telework.writing.publish', [$this->tenant, 'implementation_process']))->assertSessionHasNoErrors();
    $this->post(route('tenant.telework.writing.publish', [$this->tenant, 'family_violence']))->assertSessionHasNoErrors();

    expect(TeleworkDocument::query()->where('kind', DocumentKind::ImplementationProcess)->sole()->covered_items)->toBe(['a', 'b', 'c', 'd'])
        ->and(TeleworkDocument::query()->where('kind', DocumentKind::FamilyViolence)->sole()->covered_items)->toBe([]);
    $this->get(route('tenant.telework.route', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('rows', fn ($rows): bool => collect($rows)->firstWhere('numeral', '5.6')['state'] === 'done'
            && collect($rows)->firstWhere('numeral', '5.13')['state'] === 'done'));
});
