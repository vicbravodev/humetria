<?php

declare(strict_types=1);

namespace App\Modules\Telework\Exceptions;

use RuntimeException;

/**
 * The portal link opens nothing. One answer for every cause (unknown or replaced link, a deactivated employee, a company
 * that may not be used), so a link says nothing about who or what exists.
 */
final class PortalUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Portal unavailable.');
    }
}
