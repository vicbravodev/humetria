<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Http\Controllers\ProgressController;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Interventions\Support\EvidenceStore;
use App\Modules\Interventions\Support\InterventionFiles;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(EvidenceStore::class, InterventionFiles::class, ProgressController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    Storage::fake(config()->string('filesystems.intervention_files'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
    $this->action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $item->id]);
});

function evidenceDisk(): Illuminate\Filesystem\FilesystemAdapter
{
    return Storage::disk(config()->string('filesystems.intervention_files'));
}

function attendancePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('lista.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

function progressForm(array $overrides = []): array
{
    return ['status' => 'in_progress', 'recorded_on' => '2026-11-19', 'note' => 'Hubo plática.', ...$overrides];
}

it('keeps a PDF of evidence with a step, on the private disk', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $this->campaign->id, $this->action->id]), progressForm(['evidence' => attendancePdf()]))
        ->assertSessionHasNoErrors();

    $entry = ProgressEntry::query()->sole();
    expect($entry->evidence_path)->toStartWith("evidence/{$this->tenant->id}/{$this->action->id}/")->toEndWith('.pdf');
    evidenceDisk()->assertExists((string) $entry->evidence_path);
});

it('re-encodes an image, so no metadata of the camera survives', function (string $name): void {
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $this->campaign->id, $this->action->id]), progressForm(['evidence' => UploadedFile::fake()->image($name, 30, 20)]))
        ->assertSessionHasNoErrors();

    $path = (string) ProgressEntry::query()->sole()->evidence_path;
    expect($path)->toEndWith(str_ends_with($name, 'png') ? '.png' : '.jpg');
    evidenceDisk()->assertExists($path);
    expect(getimagesizefromstring((string) evidenceDisk()->get($path)))->not->toBeFalse();
})->with(['foto.jpg', 'foto.png']);

it('takes only a PDF or a JPG/PNG image, by content and under the size limit', function (UploadedFile $file): void {
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $this->campaign->id, $this->action->id]), progressForm(['evidence' => $file]))
        ->assertSessionHasErrors('evidence');

    expect(ProgressEntry::query()->count())->toBe(0)
        ->and($this->action->fresh()?->status)->toBe(ActionStatus::Planned)
        ->and(evidenceDisk()->allFiles())->toBe([]);
})->with([
    'text posing as a PDF' => fn () => UploadedFile::fake()->createWithContent('x.pdf', 'texto plano'),
    'text posing as an image' => fn () => UploadedFile::fake()->createWithContent('x.png', 'texto plano'),
    'a document of another kind' => fn () => UploadedFile::fake()->createWithContent('x.docx', 'PK'),
    'a PDF that is too heavy' => fn () => UploadedFile::fake()->create('x.pdf', 20481, 'application/pdf'),
]);

it('refuses a corrupt image the way it refuses the wrong kind', function (): void {
    $bad = UploadedFile::fake()->createWithContent('x.png', "\x89PNG\r\n\x1a\nnot really");

    expect(fn () => resolve(EvidenceStore::class)->store($bad->getPathname(), 'evidence/x'))->toThrow(ValidationException::class);
});

it('removes the file when the step could not be written', function (): void {
    ProgramAction::updating(fn () => throw new RuntimeException('boom'));
    $file = attendancePdf();

    expect(fn () => resolve(RecordProgress::class)->handle($this->action->id, ActionStatus::Done, '2026-11-20', null, $this->hr->id, $file->getPathname()))->toThrow(RuntimeException::class)
        ->and(evidenceDisk()->allFiles())->toBe([]);
});

it('serves the evidence only to owner and RH, never cached, and audits each opening without the path', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $this->campaign->id, $this->action->id]), progressForm(['evidence' => attendancePdf()]));
    $entry = ProgressEntry::query()->sole();
    $url = route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $entry->id]);

    $response = $this->actingAs($this->hr)->get($url)->assertOk();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))->get($url)->assertOk();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get($url)->assertForbidden();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $audit = AuditEntry::query()->where('event', 'interventions.evidence_viewed')->get();
    expect($audit)->toHaveCount(2)
        ->and($audit[0]->metadata)->toEqual(['action_id' => $this->action->id, 'program_id' => $this->program?->id, 'entry_id' => $entry->id]);
});

it('sends a guest to sign in', function (): void {
    $entry = ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $this->action->id, 'evidence_path' => 'evidence/x.pdf']);

    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $entry->id]))->assertRedirect(route('login'));
});

it('answers 404 for a step without evidence, of another action, of another Programa or of another company', function (): void {
    $without = ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $this->action->id]);
    $other = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->action->item_id]);
    $elsewhere = ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $other->id, 'evidence_path' => 'evidence/x.pdf']);
    evidenceDisk()->put('evidence/x.pdf', 'contenido');
    $strangerTenant = Tenant::factory()->create();
    workIn($strangerTenant);
    $foreign = ProgressEntry::factory()->create(['tenant_id' => $strangerTenant->id, 'evidence_path' => 'evidence/y.pdf']);
    workIn($this->tenant);
    $this->actingAs($this->hr);

    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $without->id]))->assertNotFound();
    // The step exists, but not under this action.
    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $elsewhere->id]))->assertNotFound();
    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c', $this->action->id, $elsewhere->id]))->assertNotFound();
    // The step does exist for its own action.
    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $other->id, $elsewhere->id]))->assertOk();
});

it('shows that a step has evidence, and the way to open it only to who may', function (): void {
    ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $this->action->id, 'evidence_path' => 'evidence/secret-path.pdf']);
    $entry = ProgressEntry::query()->sole();
    $url = route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]);

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('actions.data.0.history.0.hasEvidence', true)
        ->where('actions.data.0.history.0.evidenceUrl', route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $this->action->id, $entry->id])));
    $response = $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url);
    $response->assertInertia(fn (Assert $page) => $page
        ->where('actions.data.0.history.0.hasEvidence', true)
        ->where('actions.data.0.history.0.evidenceUrl', null));
    expect($response->getContent())->not->toContain('secret-path');
});
