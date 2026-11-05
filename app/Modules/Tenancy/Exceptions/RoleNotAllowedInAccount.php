<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/**
 * The account's type does not hand out that role: a union has no viewer or committee member.
 */
final class RoleNotAllowedInAccount extends RuntimeException {}
