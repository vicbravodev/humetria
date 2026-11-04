<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Events\PolicyPublished;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\PublicationCenters;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the draft of the policy as the next version, for the chosen work centers (num. 5.1; criterios C-50, C-51). The
 * version is picked under a lock of the company, so two publications never share a number; the draft becomes the published
 * version (from then on it is evidence and nothing changes it); the centers are written once. Responsibles and resources are
 * required by the note of Guía IV, and HR must say they reviewed that every commitment is true in their centers. Declaring
 * fewer than a, b and c is allowed (the board warns): the norm says what a policy contemplates, it does not block a version.
 */
final readonly class PublishPolicy
{
    private const array INCISOS = ['a', 'b', 'c'];

    public function __construct(private CurrentTenant $tenant, private PublicationCenters $publication, private RecordAudit $audit) {}

    /**
     * @param  list<string>|null  $workCenterIds  Work centers in use; null means every one in use.
     * @param  list<string>  $covers  The incisos of 5.1 the policy contemplates.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException when the draft does not exist, is already published or is another company's.
     */
    public function handle(string $documentId, ?array $workCenterIds, array $covers, bool $reviewed, int $userId): Document
    {
        $centers = $this->publication->resolve($workCenterIds);
        $covers = array_values(array_unique($covers));
        sort($covers);
        $tenantId = $this->tenant->id();

        $published = DB::transaction(function () use ($documentId, $centers, $covers, $reviewed, $userId, $tenantId): Document {
            // Two publications of the same company take turns, so they never pick the same number.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["disclosure_documents:{$tenantId}:".DocumentKind::Policy->value]);
            $draft = Document::query()->whereKey($documentId)->where('status', DocumentStatus::Draft)->lockForUpdate()->firstOrFail();
            $this->check($draft, $covers, $reviewed);
            $version = Document::query()->where('kind', DocumentKind::Policy)->max('version');

            $draft->update([
                'status' => DocumentStatus::Published,
                'version' => (is_numeric($version) ? (int) $version : 0) + 1,
                'covers' => $covers,
                'published_at' => now(),
                'published_by' => $userId,
            ]);

            foreach ($centers as $center) {
                DocumentCenter::query()->create(['tenant_id' => $tenantId, 'document_id' => $draft->id, 'work_center_id' => $center]);
            }

            $record = ['document_id' => $draft->id, 'kind' => DocumentKind::Policy->value, 'version' => $draft->version];
            $this->audit->handle('disclosure.published', $draft, [...$record, 'covers' => $covers, 'work_center_ids' => $centers]);
            Log::info('disclosure.published', [...$record, 'work_center_count' => count($centers)]);
            Event::dispatch(new PolicyPublished($tenantId, $draft->id, $centers));

            return $draft;
        });

        return $published;
    }

    /**
     * @param  list<string>  $covers
     *
     * @throws ValidationException
     */
    private function check(Document $draft, array $covers, bool $reviewed): void
    {
        $errors = [];
        $text = array_column($draft->content, 'text', 'key');

        if (trim($text['responsibles'] ?? '') === '') {
            $errors['responsibles'] = 'Indica quiénes son los responsables de aplicar la política.';
        }

        if (trim($text['resources'] ?? '') === '') {
            $errors['resources'] = 'Indica con qué recursos cuenta el centro para cumplirla.';
        }

        if (array_diff($covers, self::INCISOS) !== []) {
            $errors['covers'] = 'Algún inciso no corresponde al numeral 5.1.';
        }

        if (! $reviewed) {
            $errors['reviewed'] = 'Confirma que revisaste que cada compromiso y principio es cierto en tus centros.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
