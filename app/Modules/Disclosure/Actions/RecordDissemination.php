<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\Medium;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Support\Days;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RH records a poster, bulletin, leaflet or meeting that put information in front of the people of a work center: the day
 * and the incisos of 5.7 (a to f) it covered (criterio C-52). It is what the norm names as evidence of the disclosure (num.
 * 10.2) and it counts as delivered where the page is not active. A record is never changed or deleted.
 */
final readonly class RecordDissemination
{
    private const array ITEMS = ['a', 'b', 'c', 'd', 'e', 'f'];

    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters) {}

    /**
     * @param  list<string>  $items
     * @param  string  $day  Y-m-d, in the display time zone.
     *
     * @throws ValidationException
     */
    public function handle(string $workCenterId, string $medium, array $items, string $day, string $note, int $userId): DisseminationRecord
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

        if ($errors !== [] || $kind === null) {
            throw ValidationException::withMessages($errors);
        }

        $items = array_values(array_intersect(self::ITEMS, $named));

        $record = DisseminationRecord::query()->create([
            'tenant_id' => $this->tenant->id(),
            'work_center_id' => $workCenterId,
            'medium' => $kind,
            'covered_items' => $items,
            'done_on' => $day,
            'note' => trim($note) === '' ? null : trim($note),
            'recorded_by' => $userId,
        ]);

        Log::info('disclosure.dissemination_recorded', ['record_id' => $record->id, 'work_center_id' => $workCenterId, 'medium' => $kind->value, 'covered_items' => $items]);

        return $record;
    }
}
