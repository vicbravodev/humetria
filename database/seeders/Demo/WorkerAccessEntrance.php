<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Modules\Demos\Contracts\DemoEntrance;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use LogicException;

/**
 * The door's way in is the link the demo's head of HR issues a person (WorkerAccess, `issued`): good once, it opens their
 * session and lets them create their key, which is how they then prove it is them before something delicate. It is never
 * emailed: the demo's mail does not leave, and the visitor is the one at the door.
 */
final readonly class WorkerAccessEntrance implements DemoEntrance
{
    public function __construct(private CreateAccessLink $links) {}

    public function for(string $employeeId): string
    {
        $hr = User::query()->where('email', DemoRoster::person(DemoRoster::HR)->email)->value('id');

        return $this->links->handle($employeeId, LinkKind::Issued, is_int($hr) ? $hr : throw new LogicException('The demo has no head of HR to issue its links.'))[1];
    }
}
