<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\PublishWriting;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Support\Writing\PolicyTemplate;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PublishWriting::class);

// Rediseño v2 (board V2-Politica, decisión A3): a document written with questions is published as the next version in
// every center of the company, like an uploaded one, so the route, the 10.3 rows and the acknowledgements count it.

/** Every answer of the policy, as board V2-Politica would leave them. */
const FULL_POLICY = [
    'schedule' => 'nine', 'disconnect' => 'end', 'frequency' => 'weekly', 'channels' => ['email', 'chat'], 'lactation' => 'breaks',
    'prevention' => 'yearly', 'balance' => 'boss', 'health' => ['exams'], 'notice' => 'twenty',
];

beforeEach(function (): void {
    $this->travelTo('2026-10-12 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas CDMX']);
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @param array<string, mixed> $answers */
function writtenPolicy(array $answers = FULL_POLICY, array $edits = []): DocumentDraft
{
    return DocumentDraft::factory()->create(['tenant_id' => test()->tenant->id, 'answers' => $answers, 'edits' => $edits, 'step' => 8]);
}

function publishWriting(string $kind = 'policy', array $query = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.telework.writing.publish', [test()->tenant, $kind, ...$query]));
}

it('publishes the text as the next version in every center in use, and leaves the draft for the next one', function (): void {
    Event::fake([DocumentPublished::class]);
    Log::spy();
    writtenPolicy();

    $this->actingAs($this->hr);
    publishWriting()->assertRedirect(route('tenant.telework.index', $this->tenant))->assertSessionHasNoErrors();

    $documents = TeleworkDocument::query()->orderBy('work_center_id')->get();
    $policy = $documents->first();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('work_center_id')->sort()->values()->all())->toBe(collect([$this->north->id, $this->south->id])->sort()->values()->all())
        ->and($policy->kind)->toBe(DocumentKind::Policy)
        ->and($policy->version)->toBe(1)
        ->and($policy->path)->toBeNull()
        ->and($policy->template_version)->toBe((new PolicyTemplate)->version())
        ->and($policy->covered_items)->toBe(range('a', 'l'))
        ->and($policy->uploaded_by)->toBe($this->hr->id)
        ->and($policy->body)->toHaveCount(12)
        ->and($policy->body[0])->toEqual(['key' => 'e', 'heading' => 'Horario y desconexión', 'text' => 'La jornada en teletrabajo es de 9:00 a 18:00, de lunes a viernes. Al terminar su horario, nadie está obligado a responder mensajes ni llamadas de trabajo.'])
        ->and($documents->last()->body)->toBe($policy->body)
        ->and(DocumentDraft::query()->exists())->toBeTrue();

    expect(AuditEntry::query()->where('event', 'telework.document_published')->sole()->metadata)->toMatchArray(['kind' => 'policy', 'written' => true, 'work_center_count' => 2]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'telework.document_published' && $context['written'] === true && $context['kind'] === 'policy')->once();
    Event::assertDispatched(DocumentPublished::class, fn (DocumentPublished $event): bool => $event->tenantId === $this->tenant->id
        && collect($event->documentIds)->sort()->values()->all() === $documents->pluck('id')->sort()->values()->all());
});

it('goes back to the route it came from', function (): void {
    writtenPolicy();

    $this->actingAs($this->hr);
    publishWriting(query: ['center' => $this->north->id])->assertRedirect(route('tenant.telework.route', [$this->tenant, $this->north->id]));
});

it('asks the people of each center to confirm they read it, as an uploaded policy does', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    writtenPolicy();

    $this->actingAs($this->hr);
    publishWriting();

    $document = TeleworkDocument::query()->where('work_center_id', $this->north->id)->sole();
    expect(Acknowledgement::query()->where('document_id', $document->id)->where('employee_id', $employee->id)->exists())->toBeTrue();
});

it('numbers on from the version in force, uploaded or written', function (): void {
    TeleworkDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'kind' => DocumentKind::Policy, 'version' => 1]);
    writtenPolicy();

    $this->actingAs($this->hr);
    publishWriting();
    publishWriting();

    expect(TeleworkDocument::query()->where('work_center_id', $this->north->id)->orderBy('version')->pluck('version')->all())->toBe([1, 2, 3])
        ->and(TeleworkDocument::query()->where('work_center_id', $this->south->id)->orderBy('version')->pluck('version')->all())->toBe([1, 2]);
});

it('publishes only once every question is answered', function (?array $answers): void {
    if ($answers !== null) {
        writtenPolicy($answers);
    }

    $this->actingAs($this->hr);
    publishWriting()->assertSessionHasErrors(['answers' => 'Contesta todas las preguntas antes de publicar.']);

    expect(TeleworkDocument::query()->exists())->toBeFalse();
})->with([
    'nothing written yet' => [null],
    'a single choice open' => [array_diff_key(FULL_POLICY, ['frequency' => true])],
    'a list with nothing chosen' => [[...FULL_POLICY, 'health' => []]],
]);

it('needs a center in use to publish', function (): void {
    writtenPolicy();
    $this->north->update(['archived_at' => now()]);
    $this->south->update(['archived_at' => now()]);

    $this->actingAs($this->hr);
    publishWriting()->assertSessionHasErrors(['work_center_ids' => 'Registra un centro de trabajo antes de publicar.']);

    expect(TeleworkDocument::query()->exists())->toBeFalse();
});

it('lets only whoever manages NOM-037 publish', function (TenantRole $role): void {
    writtenPolicy();

    $this->actingAs(memberOf($this->tenant, $role));
    publishWriting()->assertForbidden();

    expect(TeleworkDocument::query()->exists())->toBeFalse();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('answers 404 to publish a document that is not written with questions', function (): void {
    $this->actingAs($this->hr);

    publishWriting('inspection_report')->assertNotFound();
});

it('says on the writing page how many people will be asked to confirm and the version in force', function (): void {
    Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->south->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('publication', ['audience' => 4, 'centers' => 2, 'version' => null]));

    writtenPolicy();
    publishWriting();

    $this->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('publication.version', ['number' => 1, 'publishedOn' => '12 oct 2026']));
});

it('shows a written version as a page to read and print, in the panel and in the portal', function (): void {
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    writtenPolicy();
    $this->actingAs($this->hr);
    publishWriting();
    $document = TeleworkDocument::query()->where('work_center_id', $this->north->id)->sole();
    $token = resolve(IssuePortalLink::class)->handle($employee->id);

    $this->get(route('tenant.telework.documents.file', [$this->tenant, $document->id]))
        ->assertInertia(fn (Assert $page) => $page->component('telework/written-document')
            ->where('document.title', 'Política de Teletrabajo')
            ->where('document.owner', 'Autopartes Demo · Planta Querétaro')
            ->where('document.version', 1)
            ->where('document.publishedOn', '12 oct 2026')
            ->has('document.sections', 12)
            ->where('document.sections.0.heading', 'Horario y desconexión'));

    $this->get(route('portal.documents.file', [$token, $document->id]))
        ->assertInertia(fn (Assert $page) => $page->component('telework/portal-document')
            ->where('document.title', 'Política de Teletrabajo')
            ->where('document.owner', 'Autopartes Demo · Planta Querétaro')
            ->where('token', $token)
            ->has('document.sections', 12));
});

it('keeps either a file or a written text in each version, never both or neither', function (array $values): void {
    expect(fn () => DB::transaction(fn () => TeleworkDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, ...$values])))
        ->toThrow(QueryException::class);
})->with([
    'both' => [['body' => [['key' => 'e', 'heading' => 'H', 'text' => 'T']], 'template_version' => 'v1']],
    'neither' => [['path' => null]],
    'a text without its template version' => [['path' => null, 'body' => [['key' => 'e', 'heading' => 'H', 'text' => 'T']]]],
]);

it('counts as the policy in force on the route of each center and for the whole company', function (): void {
    TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    writtenPolicy();
    $this->actingAs($this->hr);
    publishWriting();

    $this->get(route('tenant.telework.route', [$this->tenant, $this->north->id]))
        ->assertInertia(fn (Assert $page) => $page->where('rows', fn ($rows): bool => collect($rows)->firstWhere('numeral', '5.2')['state'] === 'done'));
    $this->get(route('tenant.telework.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('company.policyCenters', 2));
});

it('publishes the sections RH rewrote as they wrote them', function (): void {
    writtenPolicy(edits: ['f' => 'Quien teletrabaja tiene los mismos derechos que quien va al centro.']);
    $this->actingAs($this->hr);

    publishWriting();

    expect(collect(TeleworkDocument::query()->firstOrFail()->body)->firstWhere('key', 'f')['text'])->toBe('Quien teletrabaja tiene los mismos derechos que quien va al centro.');
});
