<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Exceptions\NoCurrentTenant;

/**
 * The company the current request works in, resolved from its URL. Other modules read it to scope their data.
 */
interface CurrentTenant
{
    /**
     * @throws NoCurrentTenant outside a company's routes.
     */
    public function id(): string;
}
