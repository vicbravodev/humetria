<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use LogicException;

/**
 * Company data was asked for where no company is resolved. A bug, never a user error: failing closed keeps
 * a query from reading every company's rows.
 */
final class NoCurrentTenant extends LogicException
{
    public function __construct()
    {
        parent::__construct('No company is resolved for this request.');
    }
}
