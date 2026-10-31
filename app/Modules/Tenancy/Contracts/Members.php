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
     * The members whose role has the `review-workplaces` permission (owner, hr and committee), by name: who can sit on a
     * work center's Comisión de seguridad e higiene.
     *
     * @return list<MemberSummary>
     */
    public function reviewers(): array;

    /** A person of the current company by user id; null when they belong to another company or none. */
    public function find(int $userId): ?MemberSummary;
}
