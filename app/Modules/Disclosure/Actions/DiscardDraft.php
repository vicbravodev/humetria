<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Throws a draft away. A published version is evidence and is not found here: it is never deleted (num. 10.4).
 */
final readonly class DiscardDraft
{
    /**
     * @throws ModelNotFoundException when the draft does not exist, is published or is another company's.
     */
    public function handle(string $documentId): void
    {
        DB::transaction(function () use ($documentId): void {
            $draft = Document::query()->whereKey($documentId)->where('status', DocumentStatus::Draft)->lockForUpdate()->firstOrFail();
            $draft->delete();
            Log::info('disclosure.draft_discarded', ['document_id' => $draft->id, 'kind' => $draft->kind->value]);
        });
    }
}
