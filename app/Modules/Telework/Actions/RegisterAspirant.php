<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Notifications\PortalInvitation;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Registers an active employee as an aspirant to telework: their workplaces, a Sección I checklist for each, and their
 * personal portal link, emailed to them. The employee's work center is the one whose list they answer, so it must have
 * a published one.
 */
final readonly class RegisterAspirant
{
    public function __construct(
        private Roster $roster,
        private CurrentTenant $tenant,
        private Companies $companies,
        private AddWorkplace $addWorkplace,
        private IssuePortalLink $issueLink,
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

        [$teleworker, $token] = DB::transaction(function () use ($person, $percentage, $workplaces, $userId): array {
            $teleworker = $this->open($person, $percentage);

            foreach ($workplaces as $address) {
                $this->addWorkplace->handle($teleworker, $address);
            }

            $record = ['teleworker_id' => $teleworker->id, 'employee_id' => $person->id, 'work_center_id' => $person->workCenterId, 'workplaces' => count($workplaces)];
            $this->audit->handle('telework.aspirant_registered', $teleworker, [...$record, 'registered_by' => $userId]);
            Log::info('telework.aspirant_registered', $record);

            return [$teleworker, $this->issueLink->handle($person->id)];
        });

        if ($person->email !== null) {
            Notification::route('mail', $person->email)->notify(new PortalInvitation($token, $person->name, $this->companies->current()->tradeName));
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
