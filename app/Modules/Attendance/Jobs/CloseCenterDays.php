<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Jobs;

use App\Modules\Attendance\Actions\CloseDays;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The night of one work center for one of its local days, as the dispatcher claimed it (DispatchCloseDays). Safe to retry:
 * closing a day stores nothing and a week is opened once.
 */
final class CloseCenterDays implements ShouldQueue
{
    use Queueable;

    public int $tries = 3; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** @var list<int> */
    public array $backoff = [60, 300]; // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveArrayItem

    /** @param  string  $localDate  the center's today when it was claimed, Y-m-d */
    public function __construct(public readonly string $tenantId, public readonly string $workCenterId, public readonly string $localDate) {}

    public function handle(CloseDays $closeDays): void
    {
        $closeDays->center($this->tenantId, $this->workCenterId, CarbonImmutable::parse($this->localDate));
    }
}
