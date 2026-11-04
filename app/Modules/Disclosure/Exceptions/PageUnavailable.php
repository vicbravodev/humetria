<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Exceptions;

use RuntimeException;

/**
 * The link of the page of the workers does not open. It is the same for every reason (made up, replaced, switched off,
 * expired or of a company that may not be used): saying which would tell a stranger more than that it does not work.
 */
final class PageUnavailable extends RuntimeException {}
