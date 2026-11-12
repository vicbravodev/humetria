<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Policies;

use App\Models\User;
use App\Modules\Tenancy\Contracts\Members;

/** The guided tour is for the owner of the current company, no one else. */
final readonly class FirstStepsStatePolicy
{
    public function __construct(private Members $members) {}

    public function manage(User $user): bool
    {
        return $this->members->find($user->id)?->role->value === 'owner';
    }
}
