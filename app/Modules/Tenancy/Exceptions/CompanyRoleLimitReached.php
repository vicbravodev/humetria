<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/** The company already has as many roles of its own as the platform allows. */
final class CompanyRoleLimitReached extends RuntimeException {}
