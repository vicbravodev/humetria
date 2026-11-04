<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\Days;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH records, in bulk, the acknowledgments people gave on paper (or when they have no email): the day they signed and a note
 * about the sheet (criterio C-53). Only people who still have a pending request for that version, are active and have it as the
 * version in force in their center are recorded; the rest are left alone. The day is not before the publication nor in the
 * future. The note is evidence, never logged; the audit trail keeps the count, not the names.
 */
final readonly class RecordManualAcknowledgments
{
    public function __construct(private Roster $roster, private PolicyStanding $standing, private RecordAudit $audit) {}

    /**
     * @param  list<string>  $employeeIds
     * @param  string  $day  Y-m-d, in the display time zone.
     * @return int How many acknowledgments were recorded.
     *
     * @throws ValidationException when the day is before the publication or in the future.
     * @throws ModelNotFoundException when the version does not exist, is a draft or is another company's.
     */
    public function handle(string $documentId, array $employeeIds, string $day, string $note, int $userId): int
    {
        $document = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->findOrFail($documentId);
        $this->checkDay($document, $day);

        return DB::transaction(function () use ($document, $employeeIds, $day, $note, $userId): int {
            $eligible = $this->eligible($document, $employeeIds);

            if ($eligible === []) {
                return 0;
            }

            $at = CarbonImmutable::parse($day, config()->string('app.display_timezone'))->startOfDay()->utc();

            foreach (array_chunk($eligible, 1000) as $chunk) {
                Acknowledgment::query()->where('document_id', $document->id)->whereIn('employee_id', $chunk)->update([
                    'channel' => 'manual',
                    'acknowledged_at' => $at,
                    'note' => trim($note) === '' ? null : trim($note),
                    'recorded_by' => $userId,
                ]);
            }

            $this->audit->handle('disclosure.acknowledgments_recorded', $document, ['document_id' => $document->id, 'count' => count($eligible)]);
            Log::info('disclosure.acknowledgment_recorded', ['document_id' => $document->id, 'count' => count($eligible), 'channel' => 'manual']);

            return count($eligible);
        });
    }

    /**
     * @throws ValidationException
     */
    private function checkDay(Document $document, string $day): void
    {
        $published = $document->published_at === null ? $day : $document->published_at->setTimezone(config()->string('app.display_timezone'))->toDateString();

        if ($day < $published || $day > Days::today()) {
            throw ValidationException::withMessages(['date' => 'El día no puede ser anterior a la publicación ni futuro.']);
        }
    }

    /**
     * Who is recorded: the pending, active people of the list for whom this version is the one in force.
     *
     * @param  list<string>  $employeeIds
     * @return list<string>
     */
    private function eligible(Document $document, array $employeeIds): array
    {
        $eligible = [];

        foreach (array_chunk(array_values(array_unique($employeeIds)), 1000) as $chunk) {
            $pending = array_values(Acknowledgment::query()->where('document_id', $document->id)->whereIn('employee_id', $chunk)->whereNull('acknowledged_at')->lockForUpdate()->get(['employee_id'])
                ->map(fn (Acknowledgment $ack): string => $ack->employee_id)->all());
            $people = array_filter($this->roster->findMany($pending), fn (RosterEntry $person): bool => $person->active);
            $inForce = $this->standing->inForce(array_values(array_unique(array_map(fn (RosterEntry $person): string => $person->workCenterId, $people))));

            foreach ($people as $person) {
                if (($inForce[$person->workCenterId]->documentId ?? null) === $document->id) {
                    $eligible[] = $person->id;
                }
            }
        }

        sort($eligible);

        return $eligible;
    }
}
