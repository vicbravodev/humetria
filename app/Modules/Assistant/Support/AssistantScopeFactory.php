<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Models\User;
use App\Modules\Assistant\Actions\ResolveParticipant;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/** The scope of a signed-in person in the company of the request. */
final readonly class AssistantScopeFactory
{
    /** The permissions that decide what Humi may tell a person. */
    private const array ABILITIES = [
        'use-assistant',
        'manage-organization',
        'manage-campaigns',
        'manage-telework',
        'review-workplaces',
        'view-trauma-screenings',
    ];

    public function __construct(private CurrentTenant $tenant, private ResolveParticipant $participants) {}

    public function make(User $user): AssistantScope
    {
        // Roles are read inside the company: a person who works for two keeps the roles of the last one otherwise.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return new AssistantScope(
            $this->tenant->id(),
            $this->participants->handle($user->id)->id,
            $user->id,
            array_values(array_filter(self::ABILITIES, fn (string $ability): bool => $user->can($ability))),
        );
    }
}
