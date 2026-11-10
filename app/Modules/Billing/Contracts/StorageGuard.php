<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Exceptions\StorageQuotaExceeded;

/**
 * The one rule that keeps a company inside the storage its plan includes. A module's file store calls it before it writes a
 * new file: the path is `{type}/{company}/…`, so the company is read from it. A company with no plan is not limited. Reading,
 * downloading and deleting never go through it. The nightly measure sets the true figure, so this only has to be close.
 */
interface StorageGuard
{
    /**
     * Takes `$bytes` of the company's quota, all or none, with one conditional statement.
     *
     * @throws StorageQuotaExceeded when they do not fit.
     */
    public function admit(string $path, int $bytes): void;

    /** Gives back what admit() took for a file that then could not be written. */
    public function release(string $path, int $bytes): void;
}
