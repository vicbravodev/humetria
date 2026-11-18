<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

/**
 * The files Google Ads takes to import offline click conversions (Goals → Conversions → Uploads): every request that has a
 * click id, at the time it was stored. Only the click id, never who asked. Uploading the same row twice is harmless: Google
 * drops a conversion with the same click, name and time as a duplicate, so each file always carries all of them (at most
 * 90 days, see ForgetAdClicks). The gclid goes in one file; the gbraid and wbraid of iOS, which can arrive without a
 * gclid, go in another with a column for each, one identifier per row as Google asks.
 */
final class ExportAdConversions
{
    public function handle(): string
    {
        return $this->csv([AdClickType::Gclid], 'Google Click ID', fn (DemoRequest $request): array => [$request->ad_click_id]);
    }

    public function handleIos(): string
    {
        return $this->csv([AdClickType::Gbraid, AdClickType::Wbraid], 'GBRAID,WBRAID', fn (DemoRequest $request): array => [
            $request->ad_click_type === AdClickType::Gbraid ? $request->ad_click_id : '',
            $request->ad_click_type === AdClickType::Wbraid ? $request->ad_click_id : '',
        ]);
    }

    /**
     * @param  list<AdClickType>  $types
     * @param  callable(DemoRequest): list<string|null>  $ids
     */
    private function csv(array $types, string $idColumns, callable $ids): string
    {
        $timezone = config()->string('app.display_timezone');
        $name = config()->string('services.google_ads.conversion_name');
        $lines = ["Parameters:TimeZone={$timezone}", "{$idColumns},Conversion Name,Conversion Time"];

        $requests = DemoRequest::query()
            ->whereIn('ad_click_type', $types)
            ->oldest()
            ->get(['ad_click_type', 'ad_click_id', 'created_at']);

        foreach ($requests as $request) {
            // The id is letters, digits, dashes and underscores (RememberDemoAttribution): it needs no quoting.
            $lines[] = implode(',', [...$ids($request), $name, $request->created_at->setTimezone($timezone)->format('Y-m-d H:i:s')]);
        }

        Log::info('demo_request.ad_conversions_exported', ['count' => $requests->count(), 'ios' => $types !== [AdClickType::Gclid]]);

        return implode("\n", $lines)."\n";
    }
}
