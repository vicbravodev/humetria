<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/** The role still has people or open invitations: move them to another role first. */
final class CompanyRoleInUse extends RuntimeException {}
