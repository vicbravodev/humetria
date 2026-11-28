<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

/**
 * A click id ties an ad click to a person with a name: it is kept only while Google Ads can still import it (90 days), as
 * the privacy notice promises. The rest of the request stays.
 */
final class ForgetAdClicks
{
    public const int DAYS = 90; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function handle(): int
    {
        $forgotten = DemoRequest::query()
            ->whereNotNull('ad_click_id')
            ->where('created_at', '<', now()->subDays(self::DAYS))
            ->update(['ad_click_type' => null, 'ad_click_id' => null]);

        Log::info('demo_request.ad_clicks_forgotten', ['count' => $forgotten]);

        return $forgotten;
    }
}
