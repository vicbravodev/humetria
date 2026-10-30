<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Exceptions\NoCurrentTenant;

/**
 * The company the current request works in, resolved from its URL. Other modules read it to scope their data;
 * what the person may do there is asked to the Gate (Spatie permissions such as manage-organization).
 */
interface CurrentTenant
{
    /**
     * @throws NoCurrentTenant outside a company's routes.
     */
    public function id(): string;
}
