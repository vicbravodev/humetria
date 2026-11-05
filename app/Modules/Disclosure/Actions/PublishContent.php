<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\PublicationCenters;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the draft of a block of 5.7 (b, c, d or f) as the next version of its kind, for the chosen work centers
 * (criterio C-52). Like the policy: the number is picked under a lock, the version becomes evidence and its centers are written
 * once. Unlike it, nobody is asked to confirm it: the blocks are told by being in front of the people.
 */
final readonly class PublishContent
{
    public function __construct(private CurrentTenant $tenant, private PublicationCenters $publication, private RecordAudit $audit) {}

    /**
     * @param  list<string>|null  $workCenterIds  Work centers in use; null means every one in use.
     *
     * @throws ValidationException when the text is empty or a center is not one in use.
     * @throws ModelNotFoundException when the draft does not exist, is a draft of the policy, is published or is another company's.
     */
    public function handle(string $documentId, ?array $workCenterIds, int $userId): Document
    {
        $centers = $this->publication->resolve($workCenterIds);
        $tenantId = $this->tenant->id();

        return DB::transaction(function () use ($documentId, $centers, $userId, $tenantId): Document {
            $draft = Document::query()->whereKey($documentId)->where('status', DocumentStatus::Draft)->where('kind', '!=', 'policy')->lockForUpdate()->firstOrFail();
            // Two publications of the same kind take turns, so they never pick the same number.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["disclosure_documents:{$tenantId}:{$draft->kind->value}"]);

            if (array_all($draft->content, fn (array $section): bool => trim($section['text']) === '')) {
                throw ValidationException::withMessages(['body' => 'Escribe el texto antes de publicarlo.']);
            }

            $version = Document::query()->where('kind', $draft->kind)->max('version');
            $draft->update([
                'status' => DocumentStatus::Published,
                'version' => (is_numeric($version) ? (int) $version : 0) + 1,
                'published_at' => now(),
                'published_by' => $userId,
            ]);

            foreach ($centers as $center) {
                DocumentCenter::query()->create(['tenant_id' => $tenantId, 'document_id' => $draft->id, 'work_center_id' => $center]);
            }

            $record = ['document_id' => $draft->id, 'kind' => $draft->kind->value, 'version' => $draft->version];
            $this->audit->handle('disclosure.published', $draft, [...$record, 'work_center_ids' => $centers]);
            Log::info('disclosure.published', [...$record, 'work_center_count' => count($centers)]);

            return $draft;
        });
    }
}
