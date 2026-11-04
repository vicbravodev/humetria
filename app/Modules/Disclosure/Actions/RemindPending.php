<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * «Recordar a los pendientes» (criterio C-53): asks again, by email, the active people who have not confirmed a version and
 * can be reached. The reminder is one per person while it waits, so pressing it twice does not send two emails; it lists what is
 * pending when it runs, so a person who confirms in the meantime gets nothing.
 */
final readonly class RemindPending
{
    private const int CHUNK = 500;

    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    /**
     * @return int How many people were queued to be reminded.
     *
     * @throws ModelNotFoundException when the version does not exist, is a draft or is another company's.
     */
    public function handle(string $documentId): int
    {
        $document = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->findOrFail($documentId);
        $pending = array_values(Acknowledgment::query()->where('document_id', $document->id)->whereNull('acknowledged_at')->get(['employee_id'])->map(fn (Acknowledgment $ack): string => $ack->employee_id)->all());
        $queued = 0;

        foreach (array_chunk($pending, self::CHUNK) as $chunk) {
            $reachable = array_keys(array_filter($this->roster->findMany($chunk), fn (RosterEntry $person): bool => $person->active && $person->email !== null));
            array_map(fn (string $id) => SendPolicyReminder::remind($this->tenant->id(), $id), $reachable);
            $queued += count($reachable);
        }

        Log::info('disclosure.reminders_queued', ['document_id' => $document->id, 'count' => $queued]);

        return $queued;
    }
}
