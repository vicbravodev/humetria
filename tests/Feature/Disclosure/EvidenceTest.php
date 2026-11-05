<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Disclosure\Actions\RecordDissemination;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\DisclosureFiles;
use App\Modules\Disclosure\Support\DisseminationPages;
use App\Modules\Disclosure\Support\EvidenceStore;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RecordDissemination::class, EvidenceStore::class, DisclosureFiles::class, DisseminationPages::class);

// Rediseño v2 (board V4-Difusion-Registro, decisión A11): «Cómo pruebas que lo difundiste». Who confirmed the policy by center,
// the paper signatures with the scanned sheet and how many signed, the physical means with their photo or PDF, and the results
// for the people by center (num. 5.7, 7.8 and 10.2). A sheet of signatures carries names: only whoever manages sees it, and it is
// audited.

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    Storage::fake(config()->string('filesystems.disclosure_files'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
});

function scannedSheet(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('firmas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

/** @param array<string, mixed> $body */
function recordEvidence(array $body): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.disclosure.dissemination.records.store', test()->tenant), [...['work_center_id' => test()->north->id, 'covered_items' => ['a'], 'done_on' => '2026-11-18'], ...$body]);
}

it('records how many signed on paper with the scanned sheet, as the policy put in front of the people of that center', function (): void {
    $this->actingAs($this->hr);

    recordEvidence(['medium' => 'signed_list', 'signatures' => 12, 'evidence' => scannedSheet()])
        ->assertRedirect(route('tenant.disclosure.dissemination', $this->tenant))
        ->assertSessionHasNoErrors();

    $record = DisseminationRecord::query()->sole();
    expect($record->medium->value)->toBe('signed_list')
        ->and($record->medium->label())->toBe('Lista de firmas')
        ->and($record->signatures)->toBe(12)
        ->and($record->evidence_path)->not->toBeNull();
    Storage::disk(config()->string('filesystems.disclosure_files'))->assertExists($record->evidence_path ?? '');
});

it('asks a list of signatures for its sheet and how many signed', function (array $body, string $field): void {
    $this->actingAs($this->hr);

    recordEvidence(['medium' => 'signed_list', ...$body])->assertSessionHasErrors($field);

    expect(DisseminationRecord::query()->count())->toBe(0);
})->with([
    'without the sheet' => [['signatures' => 3], 'evidence'],
    'without how many signed' => [['evidence' => scannedSheet()], 'signatures'],
    'nobody signed' => [['signatures' => 0, 'evidence' => scannedSheet()], 'signatures'],
]);

it('keeps the photo or PDF of a physical means, and takes it as optional', function (): void {
    $this->actingAs($this->hr);

    recordEvidence(['medium' => 'poster', 'evidence' => UploadedFile::fake()->image('cartel.jpg', 40, 30)])->assertSessionHasNoErrors();
    recordEvidence(['medium' => 'meeting'])->assertSessionHasNoErrors();

    // A meeting, then a poster.
    expect(DisseminationRecord::query()->orderBy('medium')->pluck('evidence_path')->map(fn (?string $path): bool => $path !== null)->all())->toBe([false, true]);
});

it('refuses a file that is not a PDF nor an image', function (): void {
    $this->actingAs($this->hr);

    recordEvidence(['medium' => 'poster', 'evidence' => UploadedFile::fake()->createWithContent('x.pdf', 'texto plano')])->assertSessionHasErrors('evidence');
});

it('shows its file to whoever manages the disclosure, audited, and a 404 for one of another company', function (): void {
    $this->actingAs($this->hr);
    recordEvidence(['medium' => 'signed_list', 'signatures' => 4, 'evidence' => scannedSheet()]);
    $record = DisseminationRecord::query()->sole();
    $url = route('tenant.disclosure.dissemination.records.evidence', [$this->tenant, $record->id]);

    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(AuditEntry::query()->where('event', 'disclosure.evidence_viewed')->count())->toBe(1);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
    $other = Tenant::factory()->create(['slug' => 'other']);
    $this->actingAs(memberOf($other, TenantRole::Hr))->get(route('tenant.disclosure.dissemination.records.evidence', [$other, $record->id]))->assertNotFound();
});

it('gives each center who confirmed the policy in force there and where its results stand, a page of centers at a time', function (): void {
    $people = staffOf($this->tenant, $this->north, 3);
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 1, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->north->id]);
    foreach ($people as $index => $person) {
        Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id, ...($index === 0 ? ['channel' => 'portal', 'acknowledged_at' => now()] : [])]);
    }
    $this->actingAs($this->hr);
    recordEvidence(['medium' => 'signed_list', 'signatures' => 2, 'evidence' => scannedSheet()]);

    $this->get(route('tenant.disclosure.dissemination', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/dissemination')
            ->where('centers.total', 2)
            ->where('centers.data.0', ['id' => $this->north->id, 'name' => 'Planta Norte', 'acks' => ['confirmed' => 1, 'active' => 3], 'results' => ['state' => 'not_applicable', 'closedOn' => null, 'campaignId' => null]])
            ->where('centers.data.1.acks', null)
            ->where('records.data.0.mediumLabel', 'Lista de firmas')
            ->where('records.data.0.signatures', 2)
            ->where('records.data.0.evidence', route('tenant.disclosure.dissemination.records.evidence', [$this->tenant, DisseminationRecord::query()->sole()->id])));
});

it('says which center has results ready to publish', function (): void {
    staffOf($this->tenant, $this->north, 20);
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 6]);
    $campaign->update(['work_center_id' => $this->north->id, 'closed_at' => '2026-11-10 18:00:00']);
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.dissemination', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.data', fn ($centers): bool => collect($centers)->firstWhere('id', $this->north->id)['results'] === ['state' => 'ready', 'closedOn' => '10 nov 2026', 'campaignId' => $campaign->id]));
});

it('keeps a list of signatures with how many signed and its sheet, and no other means with a count, in the database', function (array $attributes): void {
    $recorder = User::factory()->create();

    expect(fn () => DB::transaction(fn () => DisseminationRecord::query()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'covered_items' => ['a'], 'done_on' => '2026-11-18', 'recorded_by' => $recorder->id, ...$attributes])))
        ->toThrow(QueryException::class, 'disclosure_dissemination_records_signatures_of_a_list');
})->with([
    'a list with no count' => [['medium' => 'signed_list', 'evidence_path' => 'x.pdf', 'signatures' => null]],
    'a list with no sheet' => [['medium' => 'signed_list', 'evidence_path' => null, 'signatures' => 3]],
    'a list nobody signed' => [['medium' => 'signed_list', 'evidence_path' => 'x.pdf', 'signatures' => 0]],
    'a poster with a count' => [['medium' => 'poster', 'signatures' => 3]],
]);
