<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Jobs\SendPortalInvitation;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Registers an active employee as an aspirant to telework: their workplaces, a Sección I checklist for each, and their
 * personal portal link, made and emailed by a queued job (only ids travel in it). Without an email, RH hands a link over from the page. The employee's work center is the one whose list they answer, so it must have
 * a published one.
 */
final readonly class RegisterAspirant
{
    public function __construct(
        private Roster $roster,
        private CurrentTenant $tenant,
        private AddWorkplace $addWorkplace,
        private RecordAudit $audit,
    ) {}

    /**
     * @param  list<AddressData>  $workplaces
     *
     * @throws ValidationException
     */
    public function handle(string $employeeId, int $percentage, array $workplaces, int $userId): Teleworker
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            throw ValidationException::withMessages(['employee_id' => 'Elige a una persona activa de este centro de trabajo.']);
        }

        if ($workplaces === []) {
            throw ValidationException::withMessages(['workplaces' => 'Agrega al menos un lugar de trabajo.']);
        }

        if (Checklist::latestPublished($person->workCenterId) === null) {
            throw ValidationException::withMessages(['work_center' => 'La Comisión aún no publica la lista']);
        }

        $teleworker = DB::transaction(function () use ($person, $percentage, $workplaces, $userId): Teleworker {
            $teleworker = $this->open($person, $percentage);

            foreach ($workplaces as $address) {
                $this->addWorkplace->handle($teleworker, $address);
            }

            $record = ['teleworker_id' => $teleworker->id, 'employee_id' => $person->id, 'work_center_id' => $person->workCenterId, 'workplaces' => count($workplaces)];
            $this->audit->handle('telework.aspirant_registered', $teleworker, [...$record, 'registered_by' => $userId]);
            Log::info('telework.aspirant_registered', $record);

            return $teleworker;
        });

        if ($person->email !== null) {
            // The job makes the link and emails it, so the plain token never travels in the queue.
            SendPortalInvitation::dispatch($this->tenant->id(), $person->id);
        }

        return $teleworker;
    }

    private function open(RosterEntry $person, int $percentage): Teleworker
    {
        try {
            return Teleworker::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $person->id,
                'work_center_id' => $person->workCenterId,
                'status' => TeleworkerStatus::Aspirant,
                'telework_percentage' => $percentage,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_id' => 'Esta persona ya tiene un periodo de teletrabajo abierto.']);
        }
    }
}
