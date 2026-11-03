<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Support\PdfStore;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RH keeps a PDF of the center for the inspection, with its kind, its date and the incisos of Quinta it covers. It is never
 * deleted (criterio C-17).
 */
final readonly class PublishDocument
{
    public function __construct(private CurrentTenant $tenant, private PdfStore $pdfs, private SeatingFiles $files) {}

    /**
     * @param  list<string>  $incisos
     *
     * @throws ValidationException when the file is not a PDF.
     */
    public function handle(string $workCenterId, DocumentKind $kind, string $title, string $documentOn, array $incisos, string $file, int $userId): SeatingDocument
    {
        $tenantId = $this->tenant->id();
        $path = $this->pdfs->store($file, "documents/{$tenantId}/{$workCenterId}");

        try {
            $document = SeatingDocument::query()->create([
                'tenant_id' => $tenantId,
                'work_center_id' => $workCenterId,
                'kind' => $kind,
                'title' => $title,
                'document_on' => $documentOn,
                'incisos' => $incisos,
                'file_path' => $path,
                'uploaded_by' => $userId,
            ]);
        } catch (Throwable $failure) {
            $this->files->delete($path);

            throw $failure;
        }

        Log::info('seating.document_published', ['document_id' => $document->id, 'work_center_id' => $workCenterId, 'kind' => $kind->value]);

        return $document;
    }
}
