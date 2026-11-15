<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\UsageHistoryRow;
use App\Modules\Billing\Data\UsageSnapshot;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What a company may use of a metered resource in the calendar month (Mexico City) and what it has used. A caller reserves an estimate
 * before the costly call, settles with the real figure after it, or releases it when the call fails; the same key never counts twice.
 * Nothing about what was asked or answered reaches the meter: only a key, counts and the company.
 */
interface UsageMeter
{
    public const string ASSISTANT_TOKENS = 'assistant_tokens';

    /**
     * Holds `$quantity` for the call under `$key`, with one conditional statement: of two requests for the last of the quota the second
     * is refused. A key already reserved or settled is not reserved again (true).
     *
     * @return bool false when the quota of the month does not have that much left; nothing is held then.
     */
    public function reserve(string $tenantId, string $meter, int $quantity, string $key): bool;

    /** Counts what the call really used, in the month it was reserved in, and gives back the rest of the hold. A key settled or released before does nothing. */
    public function settle(string $tenantId, string $key, int $actual): void;

    /** Gives back the whole hold of a call that failed. A key settled or released before does nothing. */
    public function release(string $tenantId, string $key): void;

    /** Where the company stands this month: used, held, quota (plan plus grants) and what is left. */
    public function snapshot(string $tenantId, string $meter): UsageSnapshot;

    /**
     * The months the company has used the meter in, the latest first.
     *
     * @return LengthAwarePaginator<int, UsageHistoryRow>
     */
    public function history(string $tenantId, string $meter, int $perPage = 6, string $pageName = 'usage_page'): LengthAwarePaginator;
}
