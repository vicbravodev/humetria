<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A new file does not fit in the storage the company's plan includes. Its message is for the person: it says what still works. */
final class StorageQuotaExceeded extends RuntimeException {}
