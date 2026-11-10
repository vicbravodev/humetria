<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Illuminate\Support\Facades\DB;

/**
 * The lock of one person's vacation days, until the transaction ends: whoever reads a balance to decide and then writes
 * to the ledger takes it first, so two of them never both pass their check on the same days.
 */
final class EmployeeLock
{
    public static function of(string $employeeId): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["leave:{$employeeId}"]);
    }
}
