<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\SessionPortal;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use InvalidArgumentException;

/**
 * The telework portal of an active person of the current company, known by id, as their session would open it
 * (SessionPortal): what acts as the person without a request of theirs, like the demo's story.
 */
final readonly class OpenPortal
{
    public function __construct(private Roster $roster, private Companies $companies, private SessionPortal $portals) {}

    /** @throws InvalidArgumentException when the person is not an active person of the company */
    public function handle(string $employeeId): OpenedPortal
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            throw new InvalidArgumentException('Only an active person of the company has a portal.');
        }

        $company = $this->companies->current();

        return $this->portals->for(new WorkerIdentity($company->id, $person->id, $company->tradeName, $person->name));
    }
}
