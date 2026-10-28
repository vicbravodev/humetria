<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/**
 * The invitation was accepted, cancelled or expired before this acceptance could lock it.
 */
final class InvitationNoLongerValid extends RuntimeException {}
