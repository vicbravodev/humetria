<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\AckChannel;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH records, in bulk, the acknowledgements people gave on paper (or when they have no email): the date they signed and
 * a note about the sheet. Only employees who still have a pending request for the document are recorded; the rest are
 * left alone. The note is evidence, never logged.
 */
final readonly class RecordManualAcknowledgements
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @param  list<string>  $employeeIds
     * @return int How many acknowledgements were recorded.
     *
     * @throws ValidationException when the version was superseded by a newer one.
     */
    public function handle(string $documentId, array $employeeIds, CarbonImmutable $on, string $note, int $userId): int
    {
        return DB::transaction(function () use ($documentId, $employeeIds, $on, $note, $userId): int {
            $document = TeleworkDocument::query()->findOrFail($documentId);

            if (! TeleworkDocument::query()->inForce()->whereKey($documentId)->exists()) {
                throw ValidationException::withMessages(['document' => 'Esta versión ya no está vigente: hay una más reciente.']);
            }

            $ids = [];
            foreach (array_chunk($employeeIds, 1000) as $chunk) {
                $found = Acknowledgement::query()->where('document_id', $documentId)->whereIn('employee_id', $chunk)->whereNull('acknowledged_at')->lockForUpdate()->get(['employee_id']);
                $ids = [...$ids, ...array_filter($found->map(fn (Acknowledgement $ack): ?string => $ack->employee_id)->all(), is_string(...))];
            }
            sort($ids);

            if ($ids === []) {
                return 0;
            }

            foreach (array_chunk($ids, 1000) as $chunk) {
                Acknowledgement::query()->where('document_id', $documentId)->whereIn('employee_id', $chunk)->update([
                    'channel' => AckChannel::Manual,
                    'acknowledged_at' => $on->utc(),
                    'note' => $note,
                    'recorded_by' => $userId,
                ]);
            }

            $record = ['document_id' => $documentId, 'employee_ids' => $ids];
            $this->audit->handle('telework.acknowledgements_recorded', $document, [...$record, 'count' => count($ids), 'recorded_by' => $userId]);
            Log::info('telework.acknowledged', [...$record, 'channel' => AckChannel::Manual->value]);

            return count($ids);
        });
    }
}
