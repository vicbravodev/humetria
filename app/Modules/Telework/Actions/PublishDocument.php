<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Publishes a PDF as the next version of a NOM-037 document in each chosen work center (criterios C-19, C-21, C-23): one
 * row per center, all sharing the stored file. Nothing is ever replaced or deleted: the older versions stay.
 */
final readonly class PublishDocument
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private FileStore $files, private RecordAudit $audit) {}

    /**
     * @param  string  $storedPath  Path of the uploaded PDF, already validated; it is copied to the private disk here.
     * @param  list<string>  $workCenterIds  Work centers of the company in use.
     * @param  list<string>  $coveredItems  Items of the kind the document covers; it may be none.
     * @return list<TeleworkDocument>
     *
     * @throws ValidationException when a center is not one in use or an item does not belong to the kind.
     */
    public function handle(DocumentKind $kind, string $storedPath, array $workCenterIds, array $coveredItems, int $userId): array
    {
        // sort() numbers the centers anew right below.
        $centers = array_values(array_unique($workCenterIds)); // @pest-mutate-ignore: UnwrapArrayValues
        sort($centers);
        $items = array_values(array_unique($coveredItems));
        $this->check($kind, $centers, $items);

        $tenantId = $this->tenant->id();
        $path = "documents/{$tenantId}/".Str::uuid().'.pdf';

        try {
            $documents = DB::transaction(function () use ($kind, $storedPath, $centers, $items, $path, $tenantId, $userId): array {
                $this->store($storedPath, $path);
                $documents = [];

                foreach ($centers as $center) {
                    // Two uploads of the same kind for the same center take turns, so they never pick the same version.
                    DB::select('select pg_advisory_xact_lock(hashtext(?))', ["telework_documents:{$center}:{$kind->value}"]);
                    $latest = TeleworkDocument::query()->where('work_center_id', $center)->where('kind', $kind)->max('version');

                    $documents[] = TeleworkDocument::query()->create([
                        'tenant_id' => $tenantId,
                        'work_center_id' => $center,
                        'kind' => $kind,
                        // max() gives a numeric string or an int, and + 1 makes an int of either: the cast only narrows the type.
                        'version' => (is_numeric($latest) ? (int) $latest : 0) + 1, // @pest-mutate-ignore: RemoveIntegerCast
                        'path' => $path,
                        'covered_items' => $items,
                        'uploaded_by' => $userId,
                    ]);
                }

                $ids = array_map(fn (TeleworkDocument $document): string => $document->id, $documents);
                $record = ['document_ids' => $ids, 'kind' => $kind->value, 'versions' => array_column(array_map(fn (TeleworkDocument $document): array => [$document->work_center_id, $document->version], $documents), 1, 0), 'work_center_count' => count($documents)];
                $this->audit->handle('telework.document_published', $documents[0], $record);
                Log::info('telework.document_published', $record);
                Event::dispatch(new DocumentPublished($tenantId, $ids));

                return $documents;
            });
        } catch (Throwable $failure) {
            // The file went in before the commit: a failed publication must not leave it behind.
            $this->files->delete($path);

            throw $failure;
        }

        return $documents;
    }

    /**
     * @param  list<string>  $centers
     * @param  list<string>  $items
     */
    private function check(DocumentKind $kind, array $centers, array $items): void
    {
        if ($centers === [] || array_any($centers, fn (string $id): bool => ! Str::isUuid($id) || $this->workCenters->findActive($id) === null)) {
            throw ValidationException::withMessages(['work_center_ids' => 'Elige al menos un centro de trabajo de la empresa.']);
        }

        if (array_diff($items, $kind->coverableItems()) !== []) {
            throw ValidationException::withMessages(['covered_items' => 'Algún inciso no corresponde a este tipo de documento.']);
        }
    }

    private function store(string $file, string $path): void
    {
        $contents = file_get_contents($file);

        // Laravel turns the warning of a failed read into an ErrorException first: the check only narrows the type.
        if ($contents === false) { // @pest-mutate-ignore: FalseToTrue
            throw new RuntimeException('The uploaded document could not be read.');
        }

        $this->files->put($path, $contents);
    }
}
