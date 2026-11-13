<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/** The demo request a company was to be created from is gone, or already became another company; the message says which. */
final class DemoRequestUnavailable extends RuntimeException {}
