<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\MemberSummary;

/**
 * The people with access to the current company, as other modules need them (to assign someone to a work center).
 */
interface Members
{
    /**
     * Everyone with a role in the current company, by name.
     *
     * @return list<MemberSummary>
     */
    public function all(): array;

    /**
     * The members who hold the `review-workplaces` permission, by role or directly (owner, hr and committee), by name: who can sit on a
     * work center's Comisión de seguridad e higiene.
     *
     * @return list<MemberSummary>
     */
    public function reviewers(): array;

    /**
     * The members who hold the `handle-complaints` permission, by role or directly (owner, hr, committee and the outside handler), by
     * name: who can be designated to handle the complaints channel.
     *
     * @return list<MemberSummary>
     */
    public function complaintHandlers(): array;

    /**
     * The members who hold the `manage-complaint-channel` permission, by role or directly (owner and hr), by name: who administers the
     * complaints channel.
     *
     * @return list<MemberSummary>
     */
    public function complaintManagers(): array;

    /**
     * The members who hold the `view-trauma-screenings` permission, by role or directly (owner and hr), by name: who may see the Guía I and
     * channel the people it says need care (criterio C-28).
     *
     * @return list<MemberSummary>
     */
    public function screeningViewers(): array;

    /**
     * The members who hold the permission, by their role or given to them directly, by name.
     *
     * @return list<MemberSummary>
     */
    public function holding(string $permission): array;

    /** How many invitations of the current company are still open: not accepted, not cancelled and not expired. */
    public function pendingInvitations(): int;

    /** A person of the current company by user id; null when they belong to another company or none. */
    public function find(int $userId): ?MemberSummary;
}
