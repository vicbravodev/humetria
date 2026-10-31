<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\PublishDocument;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

mutates(PublishDocument::class, DocumentKind::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create();
    $this->south = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

/** A PDF on disk, as the controller hands it over once validated. */
function pdfOnDisk(): string
{
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, "%PDF-1.4\n%%EOF\n");

    return $path;
}

/** @param list<string> $centers @param list<string> $items @return list<TeleworkDocument> */
function publish(DocumentKind $kind, array $centers, array $items = []): array
{
    return resolve(PublishDocument::class)->handle($kind, pdfOnDisk(), $centers, $items, test()->hr->id);
}

it('publishes one version per chosen work center sharing the file', function (): void {
    Event::fake([DocumentPublished::class]);

    $documents = publish(DocumentKind::Policy, [$this->north->id, $this->south->id], ['a', 'c']);

    expect($documents)->toHaveCount(2)
        ->and(collect($documents)->pluck('work_center_id')->sort()->values()->all())->toBe(collect([$this->north->id, $this->south->id])->sort()->values()->all())
        ->and($documents[0]->path)->toBe($documents[1]->path)
        ->and($documents[0]->kind)->toBe(DocumentKind::Policy)
        ->and($documents[0]->version)->toBe(1)
        ->and($documents[0]->covered_items)->toBe(['a', 'c'])
        ->and($documents[0]->uploaded_by)->toBe($this->hr->id);
    Storage::disk(config()->string('filesystems.telework_files'))->assertExists($documents[0]->path);
    Event::assertDispatched(DocumentPublished::class, fn (DocumentPublished $event): bool => $event->tenantId === $this->tenant->id
        && collect($documents)->pluck('id')->sort()->values()->all() === collect($event->documentIds)->sort()->values()->all());
});

it('numbers versions per center and kind and keeps the old ones', function (): void {
    publish(DocumentKind::Policy, [$this->north->id]);
    publish(DocumentKind::Policy, [$this->north->id, $this->south->id]);
    publish(DocumentKind::Reversibility, [$this->north->id]);

    $versions = fn (WorkCenter $center, DocumentKind $kind): array => TeleworkDocument::query()->where('work_center_id', $center->id)->where('kind', $kind)->orderBy('version')->pluck('version')->all();

    expect($versions($this->north, DocumentKind::Policy))->toBe([1, 2])
        ->and($versions($this->south, DocumentKind::Policy))->toBe([1])
        ->and($versions($this->north, DocumentKind::Reversibility))->toBe([1])
        ->and(TeleworkDocument::query()->count())->toBe(4);
});

it('keeps every version when published again, each with its own file', function (): void {
    $first = publish(DocumentKind::Policy, [$this->north->id])[0];
    $second = publish(DocumentKind::Policy, [$this->north->id])[0];

    expect($second->path)->not->toBe($first->path);
    Storage::disk(config()->string('filesystems.telework_files'))->assertExists([$first->path, $second->path]);
});

it('enforces one version number per center and kind in the database', function (): void {
    $document = publish(DocumentKind::Policy, [$this->north->id])[0];

    expect(fn () => TeleworkDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'kind' => DocumentKind::Policy, 'version' => $document->version]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('knows which items each kind can cover', function (): void {
    expect(DocumentKind::Policy->coverableItems())->toBe(range('a', 'l'))
        ->and(DocumentKind::ImplementationProcess->coverableItems())->toBe(range('a', 'd'))
        ->and(DocumentKind::RiskInformation->coverableItems())->toBe([])
        ->and(DocumentKind::MaintenanceProgram->coverableItems())->toBe([])
        ->and(DocumentKind::Reversibility->coverableItems())->toBe([])
        ->and(DocumentKind::FamilyViolence->coverableItems())->toBe([])
        ->and(DocumentKind::CommitteeParticipation->coverableItems())->toBe([])
        ->and(DocumentKind::InspectionReport->coverableItems())->toBe([])
        ->and(DocumentKind::Policy->items())->toHaveCount(12)
        ->and(DocumentKind::ImplementationProcess->items())->toHaveCount(4)
        ->and(DocumentKind::Policy->label())->toBe('Política de Teletrabajo');
});

it('rejects covered items that do not belong to the kind and stores nothing', function (DocumentKind $kind, array $items): void {
    expect(fn () => publish($kind, [$this->north->id], $items))->toThrow(ValidationException::class);

    expect(TeleworkDocument::query()->count())->toBe(0)
        ->and(Storage::disk(config()->string('filesystems.telework_files'))->allFiles())->toBe([]);
})->with([
    'beyond l' => [DocumentKind::Policy, ['m']],
    'process beyond d' => [DocumentKind::ImplementationProcess, ['e']],
    'a kind without items' => [DocumentKind::Reversibility, ['a']],
    'not a letter' => [DocumentKind::Policy, ['zz']],
]);

it('rejects an empty choice of centers, a center of another company or an archived one', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->create(['archived_at' => now()]);

    foreach ([[], [$foreign->id], [$archived->id], ['no-uuid']] as $centers) {
        expect(fn () => publish(DocumentKind::Policy, $centers))->toThrow(ValidationException::class);
    }

    expect(TeleworkDocument::query()->count())->toBe(0);
});

it('deletes the stored file when the database refuses', function (): void {
    DB::statement('ALTER TABLE telework_documents ADD CONSTRAINT refuse_all CHECK (false)');

    expect(fn () => publish(DocumentKind::Policy, [$this->north->id]))->toThrow(Illuminate\Database\QueryException::class);

    expect(Storage::disk(config()->string('filesystems.telework_files'))->allFiles())->toBe([]);
});

it('records the version of each center when their histories differ', function (): void {
    publish(DocumentKind::Policy, [$this->north->id]);
    publish(DocumentKind::Policy, [$this->north->id]);
    publish(DocumentKind::Policy, [$this->north->id, $this->south->id]);

    $entry = AuditEntry::query()->where('event', 'telework.document_published')->get()->first(fn (AuditEntry $entry): bool => $entry->metadata['work_center_count'] === 2);
    expect($entry->metadata['versions'])->toEqual([$this->north->id => 3, $this->south->id => 1])
        ->and($entry->metadata)->not->toHaveKey('version');
});

it('audits and logs the publication without file names', function (): void {
    Log::spy();

    $documents = publish(DocumentKind::Policy, [$this->north->id, $this->south->id], ['a']);

    $entry = AuditEntry::query()->where('event', 'telework.document_published')->sole();
    expect($entry->metadata)->toMatchArray(['kind' => 'policy', 'work_center_count' => 2])
        ->and($entry->metadata['versions'])->toEqual([$this->north->id => 1, $this->south->id => 1])
        ->and($entry->metadata['document_ids'])->toHaveCount(2)
        ->and(json_encode($entry->metadata))->not->toContain($documents[0]->path);
    Log::shouldHaveReceived('info')->with('telework.document_published', Mockery::on(fn (array $context): bool => $context['kind'] === 'policy'
        && $context['versions'] === [$this->north->id => 1, $this->south->id => 1]
        && $context['work_center_count'] === 2
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $documents[0]->path)))->once();
});
