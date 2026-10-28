<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/**
 * An existing account tried to accept an invitation sent to another email: the link never takes an account over.
 */
final class InvitationForAnotherAccount extends RuntimeException {}
