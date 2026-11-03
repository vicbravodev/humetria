<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Events\PersonInputSubmitted;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Stores what a person answered about their footwear and discomfort, with the moment they accepted the privacy notice
 * (criterio C-18). The log and the event carry ids only; the answer, never.
 */
final readonly class SubmitPersonInput
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    public function handle(PortalIdentity $person, int $footwear, int $discomfort, ?string $note): PersonInput
    {
        // The identity was opened a moment ago, so the person is in the roster.
        $workCenterId = ($this->roster->find($person->employeeId) ?? throw new InvalidArgumentException('The person is not in the roster.'))->workCenterId;

        $now = now();
        $input = PersonInput::query()->create([
            'tenant_id' => $this->tenant->id(),
            'employee_id' => $person->employeeId,
            'work_center_id' => $workCenterId,
            'footwear' => $footwear,
            'discomfort' => $discomfort,
            'note' => $note,
            'notice_accepted_at' => $now,
            'submitted_at' => $now,
        ]);

        Log::info('seating.person_input_submitted', ['employee_id' => $person->employeeId, 'work_center_id' => $workCenterId]);
        Event::dispatch(new PersonInputSubmitted($person->employeeId, $workCenterId));

        return $input;
    }
}
