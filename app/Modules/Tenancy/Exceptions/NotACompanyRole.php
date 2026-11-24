<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

/** The role is a platform template (or has no company): only a company's own roles can be edited or deleted. */
final class NotACompanyRole extends RuntimeException {}
