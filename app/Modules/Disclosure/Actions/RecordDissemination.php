<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\Medium;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Support\Days;
use App\Modules\Disclosure\Support\DisclosureFiles;
use App\Modules\Disclosure\Support\EvidenceStore;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RH records a poster, bulletin, leaflet or meeting that put information in front of the people of a work center: the day
 * and the incisos of 5.7 (a to f) it covered (criterio C-52), with its photo or PDF if it has one; or a list of signatures on
 * paper, with how many signed and the scanned sheet (decisión A11). It is what the norm names as evidence of the disclosure (num.
 * 10.2) and it counts as delivered where the page is not active. A record is never changed or deleted.
 */
final readonly class RecordDissemination
{
    private const array ITEMS = ['a', 'b', 'c', 'd', 'e', 'f'];

    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private EvidenceStore $evidence, private DisclosureFiles $files) {}

    /**
     * @param  list<string>  $items
     * @param  string  $day  Y-m-d, in the display time zone.
     * @param  string|null  $evidenceFile  Path of the uploaded photo or PDF; required, the scanned sheet, for a list of signatures.
     * @param  int|null  $signatures  How many signed a list of signatures; ignored for any other means.
     *
     * @throws ValidationException
     */
    public function handle(string $workCenterId, string $medium, array $items, string $day, string $note, int $userId, ?string $evidenceFile = null, ?int $signatures = null): DisseminationRecord
    {
        $kind = Medium::tryFrom($medium);
        $named = array_values(array_unique($items));
        $errors = [];

        if (! Str::isUuid($workCenterId) || $this->workCenters->findActive($workCenterId) === null) {
            $errors['work_center_id'] = 'Elige un centro de trabajo de la empresa.';
        }

        if ($kind === null) {
            $errors['medium'] = 'Elige cómo se dio a conocer.';
        }

        if ($named === [] || array_diff($named, self::ITEMS) !== []) {
            $errors['covered_items'] = 'Elige al menos un inciso del numeral 5.7 (a a f).';
        }

        if ($day > Days::today()) {
            $errors['done_on'] = 'El día no puede ser futuro.';
        }

        $list = $kind === Medium::SignedList;

        if ($list && ($signatures === null || $signatures < 1)) {
            $errors['signatures'] = 'Escribe cuántas personas firmaron.';
        }

        if ($list && $evidenceFile === null) {
            $errors['evidence'] = 'Sube la hoja firmada, escaneada o en foto.';
        }

        if ($errors !== [] || $kind === null) {
            throw ValidationException::withMessages($errors);
        }

        $items = array_values(array_intersect(self::ITEMS, $named));

        $stored = $evidenceFile === null ? null : $this->evidence->store($evidenceFile, "dissemination/{$this->tenant->id()}");

        try {
            $record = DisseminationRecord::query()->create([
                'tenant_id' => $this->tenant->id(),
                'work_center_id' => $workCenterId,
                'medium' => $kind,
                'covered_items' => $items,
                'done_on' => $day,
                'note' => trim($note) === '' ? null : trim($note),
                'evidence_path' => $stored,
                'signatures' => $list ? $signatures : null,
                'recorded_by' => $userId,
            ]);
        } catch (Throwable $failure) {
            // A file nobody points at is not evidence: it goes with the record that failed.
            $this->files->delete(...array_filter([$stored]));

            throw $failure;
        }

        Log::info('disclosure.dissemination_recorded', ['record_id' => $record->id, 'work_center_id' => $workCenterId, 'medium' => $kind->value, 'covered_items' => $items]);

        return $record;
    }
}
