<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Exceptions\NoCurrentTenant;

/**
 * The company the current request works in, resolved from its URL, and what the person in it may do.
 * Other modules read it to scope their data and to authorize.
 */
interface CurrentTenant
{
    /**
     * @throws NoCurrentTenant outside a company's routes.
     */
    public function id(): string;

    /** Work centers and employees: add, correct, archive, and see employees' contact data. */
    public function canManageOrganization(): bool;

    /** Invite people, change their role and remove their access. */
    public function canManageMembers(): bool;
}
