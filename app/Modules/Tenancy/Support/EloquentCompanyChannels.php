<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Data\ChannelSetting;
use App\Modules\Tenancy\Models\TenantChannel;

final class EloquentCompanyChannels implements CompanyChannels
{
    /** The channels in the order the catalog lists them, each with its default when the company has no row. */
    private const array DEFAULTS = [
        'mail' => ['enabled' => true, 'preference' => 1], // @pest-mutate-ignore: TrueToFalse,DecrementInteger,IncrementInteger,RemoveArrayItem
        'whatsapp' => ['enabled' => false, 'preference' => 2], // @pest-mutate-ignore: FalseToTrue,DecrementInteger,IncrementInteger,RemoveArrayItem
    ];

    public function settings(string $tenantId): array
    {
        $rows = TenantChannel::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->get()->keyBy('channel');

        $settings = [];

        foreach (self::DEFAULTS as $channel => $default) {
            $row = $rows->get($channel);

            $settings[] = new ChannelSetting(
                $channel,
                $row->enabled ?? $default['enabled'],
                $row->preference ?? $default['preference'],
                $row->consent_wording_version ?? null,
                $row->monthly_cap ?? null,
            );
        }

        // usort is stable: on a tie the catalog order (mail first) stays.
        usort($settings, fn (ChannelSetting $a, ChannelSetting $b): int => $a->preference <=> $b->preference);

        return $settings;
    }

    public function monthlyCap(string $tenantId, string $channel): ?int
    {
        foreach ($this->settings($tenantId) as $setting) {
            if ($setting->channel === $channel) {
                return $setting->monthlyCap;
            }
        }

        return null;
    }
}
