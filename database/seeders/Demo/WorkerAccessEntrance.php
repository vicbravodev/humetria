<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Contracts\DemoEntrance;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use InvalidArgumentException;
use LogicException;

/**
 * The door's way in is the link the demo's head of HR issues a person (WorkerAccess, `issued`): good once, it opens their
 * session and lets them create their key, which is how they then prove it is them before something delicate. It is never
 * emailed: the demo's mail does not leave, and the visitor is the one at the door. It works only inside the demo and only
 * for a person of the door's pool: it issues as the demo's head of HR, so it must never issue anything for anyone else.
 */
final readonly class WorkerAccessEntrance implements DemoEntrance
{
    public function __construct(private CreateAccessLink $links, private Companies $companies, private DemoCompanies $demos, private DemoDoorPool $pool) {}

    public function for(string $employeeId): string
    {
        if ($this->demos->id() === null || $this->demos->id() !== $this->companies->current()->id) {
            throw new LogicException('The door issues ways in only inside the demo company.');
        }

        if (! in_array($employeeId, $this->pool->employeeIds(), true)) {
            throw new InvalidArgumentException('That person is not one the door hands out.');
        }

        $hr = User::query()->where('email', DemoRoster::person(DemoRoster::HR)->email)->value('id');

        return $this->links->handle($employeeId, LinkKind::Issued, is_int($hr) ? $hr : throw new LogicException('The demo has no head of HR to issue its links.'))[1];
    }
}
