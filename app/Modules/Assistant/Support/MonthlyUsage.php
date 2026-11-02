<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Models\AiUsage;
use Carbon\CarbonImmutable;

/** The tokens the current company has used with Humi in the calendar month, which starts at midnight in Mexico City. */
final class MonthlyUsage
{
    public function tokens(): int
    {
        // Rows are stored in UTC.
        $start = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfMonth()->utc();
        $row = AiUsage::query()->toBase()->where('created_at', '>=', $start)->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) AS total')->first();

        return filter_var($row->total ?? 0, FILTER_VALIDATE_INT) ?: 0;
    }
}
