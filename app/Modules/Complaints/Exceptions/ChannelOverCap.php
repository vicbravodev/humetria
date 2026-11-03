<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Exceptions;

use RuntimeException;

/** The channel already received today as many complaints as its daily cap allows. */
final class ChannelOverCap extends RuntimeException {}
