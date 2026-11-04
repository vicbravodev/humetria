<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Exceptions;

use RuntimeException;

/** No usable channel answers to that code: it does not exist, was never activated, or its company may not be used. */
final class ChannelUnavailable extends RuntimeException {}
