<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

/**
 * The file Google Ads takes to import offline click conversions (Goals → Conversions → Uploads): every request that has a
 * gclid, at the time it was stored. Only the click id, never who asked. Uploading the same row twice is harmless: Google
 * drops a conversion with the same click, name and time as a duplicate, so the file always carries all of them (at most
 * 90 days, see ForgetAdClicks). The gbraid and wbraid of iOS do not go: the click import does not take them.
 */
final class ExportAdConversions
{
    public function handle(): string
    {
        $timezone = config()->string('app.display_timezone');
        $name = config()->string('services.google_ads.conversion_name');
        $lines = ["Parameters:TimeZone={$timezone}", 'Google Click ID,Conversion Name,Conversion Time'];

        $requests = DemoRequest::query()
            ->where('ad_click_type', AdClickType::Gclid)
            ->oldest()
            ->get(['ad_click_id', 'created_at']);

        foreach ($requests as $request) {
            // The id is letters, digits, dashes and underscores (RememberDemoAttribution): it needs no quoting.
            $lines[] = implode(',', [$request->ad_click_id, $name, $request->created_at->setTimezone($timezone)->format('Y-m-d H:i:s')]);
        }

        Log::info('demo_request.ad_conversions_exported', ['count' => $requests->count()]);

        return implode("\n", $lines)."\n";
    }
}
