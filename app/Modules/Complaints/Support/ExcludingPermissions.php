<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Tenancy\Contracts\Members;

/** Which of the permissions that keep someone from a kind of complaint (criterio C-45) each person of the company holds. */
final readonly class ExcludingPermissions
{
    public function __construct(private Members $members) {}

    /**
     * @return array<int, list<string>> by user id; nobody listed holds none.
     */
    public function byMember(): array
    {
        $held = [];

        foreach (RespondentKind::cases() as $kind) {
            $permission = $kind->excludedPermission();

            if ($permission !== null) {
                foreach ($this->members->holding($permission) as $member) {
                    $held[$member->userId][] = $permission;
                }
            }
        }

        return $held;
    }
}
