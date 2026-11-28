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
        'use-assistant', // @pest-mutate-ignore: RemoveArrayItem
        'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'manage-organization', // @pest-mutate-ignore: RemoveArrayItem
        'manage-campaigns', // @pest-mutate-ignore: RemoveArrayItem
        'manage-telework', // @pest-mutate-ignore: RemoveArrayItem
        'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'view-trauma-screenings', // @pest-mutate-ignore: RemoveArrayItem
        'handle-complaints', // @pest-mutate-ignore: RemoveArrayItem
        'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        // The areas Humi tells about: each only to whoever sees it in the panel.
        'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'view-interventions', // @pest-mutate-ignore: RemoveArrayItem
        'view-disclosure', // @pest-mutate-ignore: RemoveArrayItem
        'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'view-work-centers', // @pest-mutate-ignore: RemoveArrayItem
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
