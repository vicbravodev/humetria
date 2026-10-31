<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Support\WorkplaceStanding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Moves an aspirant into the roster (numerals 5.1 and 5.4): all the data of 5.1 and at least one workplace that is
 * validated and in force. The equipment (datum k) is, until its list exists, the reason for having none.
 */
final readonly class JoinRoster
{
    public function __construct(private WorkplaceStanding $standing, private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(Teleworker $teleworker, RosterData $data, int $userId): void
    {
        DB::transaction(function () use ($teleworker, $data, $userId): void {
            // Locked, so two joins at once cannot both pass the status check.
            $current = Teleworker::query()->lockForUpdate()->findOrFail($teleworker->id);

            $errors = $this->missing($data);

            if ($current->status !== TeleworkerStatus::Aspirant) {
                $errors['teleworker'] = 'Esta persona ya está en el padrón o su periodo terminó.';
            } elseif (! $this->hasWorkplaceInForce($current)) {
                $errors['workplaces'] = 'Necesita al menos un lugar validado y vigente.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $home = $data->home;
            $current->update([
                'status' => TeleworkerStatus::Active,
                'started_at' => now(),
                'marital_status' => $data->maritalStatus,
                'activities' => $data->activities,
                'job_profile' => $data->jobProfile,
                'contact_phone' => $data->contactPhone,
                'no_equipment_reason' => $data->noEquipmentReason,
                ...($home?->attributes() ?? []),
            ]);

            $record = ['teleworker_id' => $current->id, 'employee_id' => $current->employee_id, 'work_center_id' => $current->work_center_id];
            $this->audit->handle('telework.teleworker_joined', $current, [...$record, 'joined_by' => $userId]);
            Log::info('telework.teleworker_joined', $record);
        });
    }

    /** @return array<string, string> */
    private function missing(RosterData $data): array
    {
        $blank = fn (?string $value): bool => $value === null || trim($value) === '';
        $required = [
            'marital_status' => $data->maritalStatus === null,
            'activities' => $blank($data->activities),
            'job_profile' => $blank($data->jobProfile),
            'contact_phone' => $blank($data->contactPhone),
            'street' => $blank($data->home?->street),
            'exterior_number' => $blank($data->home?->exteriorNumber),
            'neighborhood' => $blank($data->home?->neighborhood),
            'postal_code' => $blank($data->home?->postalCode),
            'municipality' => $blank($data->home?->municipality),
            'state' => $blank($data->home?->state),
            // ponytail: no equipment table until Task 13, which relaxes this to "equipment registered or a reason".
            'no_equipment_reason' => $blank($data->noEquipmentReason),
        ];

        return array_map(fn (): string => 'Este dato es obligatorio.', array_filter($required));
    }

    private function hasWorkplaceInForce(Teleworker $teleworker): bool
    {
        foreach ($teleworker->workplaces()->get() as $workplace) {
            if (in_array($this->standing->of($workplace), [WorkplaceStatus::Valid, WorkplaceStatus::ValidWithPlan], true)) {
                return true;
            }
        }

        return false;
    }
}
