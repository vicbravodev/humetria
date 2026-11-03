<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\PublishDocument;
use App\Modules\Seating\Actions\RecordAssessmentInMinutes;
use App\Modules\Seating\Http\Controllers\DocumentController;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PublishDocument::class, RecordAssessmentInMinutes::class, DocumentController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.seating_files'));
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function documentPdf(string $name = 'acta.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function documentForm(array $overrides = []): array
{
    return ['kind' => 'commission_minutes', 'title' => 'Acta de recorrido de febrero', 'document_on' => '2026-02-27', 'incisos' => ['B', 'C'], 'file' => documentPdf(), ...$overrides];
}

function documentsUrl(): string
{
    return route('tenant.seating.documents.store', [test()->tenant, test()->center->id]);
}

it('publishes a PDF of the center with its type, date and the incisos it covers', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    $this->post(documentsUrl(), documentForm())->assertRedirect()->assertSessionHasNoErrors();

    $document = SeatingDocument::query()->sole();
    expect($document->work_center_id)->toBe($this->center->id)->and($document->kind->value)->toBe('commission_minutes')
        ->and($document->title)->toBe('Acta de recorrido de febrero')->and($document->document_on->toDateString())->toBe('2026-02-27')
        ->and($document->incisos)->toBe(['B', 'C'])->and($document->uploaded_by)->toBe($this->hr->id);
    Storage::disk(config()->string('filesystems.seating_files'))->assertExists((string) $document->file_path);
    Log::shouldHaveReceived('info')->with('seating.document_published', ['document_id' => $document->id, 'work_center_id' => $this->center->id, 'kind' => 'commission_minutes'])->once();
});

it('takes only PDFs, by content', function (): void {
    $this->actingAs($this->hr);

    $this->post(documentsUrl(), documentForm(['file' => UploadedFile::fake()->createWithContent('acta.pdf', 'esto no es un PDF')]))->assertSessionHasErrors('file');
    $this->post(documentsUrl(), documentForm(['file' => UploadedFile::fake()->image('acta.jpg')]))->assertSessionHasErrors('file');

    expect(SeatingDocument::query()->count())->toBe(0);
});

it('rejects what a document cannot be', function (array $bad, string $field): void {
    $this->actingAs($this->hr);

    $this->post(documentsUrl(), documentForm($bad))->assertSessionHasErrors($field);

    expect(SeatingDocument::query()->count())->toBe(0);
})->with([
    'unknown kind' => [['kind' => 'poem'], 'kind'],
    'no title' => [['title' => ' '], 'title'],
    'a future date' => [['document_on' => '2026-03-02'], 'document_on'],
    'an unknown inciso' => [['incisos' => ['Z']], 'incisos.0'],
    'no file' => [['file' => null], 'file'],
]);

it('lists the documents of the center paginated, to RH and the Comisión of the center, and not to the viewer', function (): void {
    SeatingDocument::factory()->count(17)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $url = route('tenant.seating.documents.index', [$this->tenant, $this->center->id]);

    $this->actingAs($this->cora)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('seating/documents')->where('documents.total', 17)->where('documents.per_page', 15)->has('documents.data', 15)->where('canManage', false));
    $this->get($url.'?page=2')->assertInertia(fn (Assert $page) => $page->has('documents.data', 2));
    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('canManage', true));
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
});

it('lets only manage-seating publish', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(documentsUrl(), documentForm())->assertForbidden();

    expect(SeatingDocument::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('serves the file only to who may see the center, never cached', function (): void {
    $this->actingAs($this->hr)->post(documentsUrl(), documentForm());
    $document = SeatingDocument::query()->sole();
    $url = route('tenant.seating.documents.file', [$this->tenant, $document->id]);

    $response = $this->actingAs($this->cora)->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $elsewhere->id]);
    $this->actingAs($elsewhere)->get($url)->assertNotFound();
});

it('links a signed assessment to a commission minutes document of its center', function (): void {
    Log::spy();
    $assessment = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $minutes = SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($this->cora);

    $this->post(route('tenant.seating.assessments.minutes', [$this->tenant, $assessment->id]), ['document_id' => $minutes->id])->assertRedirect()->assertSessionHasNoErrors();

    expect($assessment->fresh()?->recorded_in_document_id)->toBe($minutes->id)->and($assessment->fresh()?->status->value)->toBe('signed');
    Log::shouldHaveReceived('info')->with('seating.assessment_recorded', ['assessment_id' => $assessment->id, 'document_id' => $minutes->id, 'work_center_id' => $this->center->id])->once();
});

it('refuses to link a draft, another kind of document, a document of another center, or by someone who cannot assess', function (string $case): void {
    $assessment = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $minutes = match ($case) {
        'draft' => (function () use (&$assessment) {
            $assessment = RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

            return SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        })(),
        'another kind' => SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => 'regulation']),
        'another center' => SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id]),
        'viewer' => SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
    };
    $this->actingAs($case === 'viewer' ? memberOf($this->tenant, TenantRole::Viewer) : $this->cora);

    $response = $this->post(route('tenant.seating.assessments.minutes', [$this->tenant, $assessment->id]), ['document_id' => $minutes->id]);

    $case === 'viewer' ? $response->assertForbidden() : $response->assertSessionHasErrors('document_id');
    expect($assessment->fresh()?->recorded_in_document_id)->toBeNull();
})->with(['draft', 'another kind', 'another center', 'viewer']);

it('offers the minutes of the center to pick from, searched on the server', function (): void {
    SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'title' => 'Acta de febrero']);
    SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'title' => 'Otra cosa', 'kind' => 'regulation']);
    SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'title' => 'Acta de marzo']);

    $all = $this->actingAs($this->cora)->getJson(route('tenant.seating.documents.options', [$this->tenant, $this->center->id]))->assertOk()->json();
    $found = $this->getJson(route('tenant.seating.documents.options', [$this->tenant, $this->center->id, 'q' => 'MARZO']))->json();

    expect(array_column($all, 'label'))->toEqualCanonicalizing(['Acta de febrero', 'Acta de marzo'])->and(array_column($found, 'label'))->toBe(['Acta de marzo']);
});

it('has no destroy route for documents', function (): void {
    $deletes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true) && str_contains((string) $route->getName(), 'seating.documents'));

    expect($deletes)->toHaveCount(0);
});

it('does not touch the documents of another company', function (): void {
    $foreign = SeatingDocument::factory()->create();
    $this->actingAs($this->hr);

    $this->get(route('tenant.seating.documents.file', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.seating.documents.index', [$this->tenant, $foreign->work_center_id]))->assertNotFound();
});

it('refuses in the database an unknown kind', function (): void {
    SeatingDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    expect(fn () => DB::transaction(fn () => DB::table('seating_documents')->update(['kind' => 'poem'])))->toThrow(QueryException::class, 'seating_documents_kind_known');
});
