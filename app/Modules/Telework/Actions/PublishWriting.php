<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Telework\Support\Writing\WrittenTemplate;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a document written with questions as the next version in every work center in use (decisión A3: written once,
 * it covers all the centers), numbered after the version in force there, uploaded or written. Like an uploaded one
 * (PublishDocument), each center gets its row and DocumentPublished asks the people to confirm. The draft stays as the
 * start of the next version.
 */
final readonly class PublishWriting
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private Writer $writer, private RecordAudit $audit) {}

    /**
     * @return list<TeleworkDocument>
     *
     * @throws ValidationException when a question is not answered or the company has no center in use.
     */
    public function handle(WrittenTemplate $template, DocumentKind $kind, int $userId): array
    {
        $draft = DocumentDraft::query()->where('kind', $kind)->first();

        if ($draft === null || ! $this->writer->complete($template, $draft->answers)) {
            throw ValidationException::withMessages(['answers' => 'Contesta todas las preguntas antes de publicar.']);
        }

        $centers = array_map(fn (WorkCenterSummary $center): string => $center->id, array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));

        if ($centers === []) {
            throw ValidationException::withMessages(['work_center_ids' => 'Registra un centro de trabajo antes de publicar.']);
        }

        sort($centers);
        $body = array_map(fn (array $section): array => ['key' => $section['key'], 'heading' => $section['heading'], 'text' => $section['text'] ?? ''], $this->writer->sections($template, $draft->answers, $draft->edits));
        $tenantId = $this->tenant->id();

        return DB::transaction(function () use ($template, $kind, $userId, $centers, $body, $tenantId): array {
            $documents = [];

            foreach ($centers as $center) {
                // Takes turns with an upload of the same kind for the same center (PublishDocument), so no version repeats.
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ["telework_documents:{$center}:{$kind->value}"]);
                $latest = TeleworkDocument::query()->where('work_center_id', $center)->where('kind', $kind)->max('version');

                $documents[] = TeleworkDocument::query()->create([
                    'tenant_id' => $tenantId,
                    'work_center_id' => $center,
                    'kind' => $kind,
                    // max() gives a numeric string or an int, and + 1 makes an int of either: the cast only narrows the type.
                    'version' => (is_numeric($latest) ? (int) $latest : 0) + 1, // @pest-mutate-ignore: RemoveIntegerCast
                    'body' => $body,
                    'template_version' => $template->version(),
                    'covered_items' => $kind->coverableItems(),
                    'uploaded_by' => $userId,
                ]);
            }

            $ids = array_map(fn (TeleworkDocument $document): string => $document->id, $documents);
            $record = ['document_ids' => $ids, 'kind' => $kind->value, 'written' => true, 'work_center_count' => count($documents)];
            $this->audit->handle('telework.document_published', $documents[0], $record);
            Log::info('telework.document_published', $record);
            Event::dispatch(new DocumentPublished($tenantId, $ids));

            return $documents;
        });
    }
}
