<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Data\ChannelSettingsData;
use App\Modules\Tenancy\Models\TenantChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saves "Canales de comunicación": each channel on or off, which goes first and the owner's cap of WhatsApp messages. Turning
 * WhatsApp on (or accepting the words again, when their version changed) keeps who, when and which version, and leaves an audit
 * entry; turning it off leaves one too, and keeps the trace. A channel with no row that stays as it is by default gets none.
 */
final readonly class UpdateCompanyChannels
{
    /** The defaults of a channel with no row (CompanyChannels): mail on and first, WhatsApp off and second. */
    private const array DEFAULTS = ['mail' => true, 'whatsapp' => false];

    public function __construct(private RecordAudit $audit) {}

    public function handle(string $tenantId, ChannelSettingsData $data, ?int $userId): void
    {
        DB::transaction(function () use ($tenantId, $data, $userId): void {
            foreach (self::DEFAULTS as $channel => $defaultEnabled) {
                $this->save($tenantId, $channel, $defaultEnabled, $data, $userId);
            }
        });

        Log::info('tenancy.channels_updated', ['mail' => $data->mailEnabled, 'whatsapp' => $data->whatsAppEnabled, 'preferred' => $data->preferred]);
    }

    private function save(string $tenantId, string $channel, bool $defaultEnabled, ChannelSettingsData $data, ?int $userId): void
    {
        $enabled = $channel === 'whatsapp' ? $data->whatsAppEnabled : $data->mailEnabled;
        $preference = $data->preferred === $channel ? 1 : 2;
        $cap = $channel === 'whatsapp' ? $data->whatsAppMonthlyCap : null;
        $defaultPreference = $channel === 'mail' ? 1 : 2;

        $row = TenantChannel::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('channel', $channel)->first();

        // Nothing changed from what a channel without a row already is: no row.
        if ($row === null && $enabled === $defaultEnabled && $preference === $defaultPreference && $cap === null) {
            return;
        }

        $wasEnabled = $row->enabled ?? $defaultEnabled;
        $current = config()->string('whatsapp.consent.version');
        $activation = $channel === 'whatsapp' && $enabled && ($row === null || ! $wasEnabled || $row->consent_wording_version !== $current);

        $row ??= new TenantChannel(['tenant_id' => $tenantId, 'channel' => $channel]);
        $row->fill(['enabled' => $enabled, 'preference' => $preference, 'monthly_cap' => $cap]);

        if ($activation) {
            $row->fill(['enabled_by' => $userId, 'enabled_at' => now(), 'consent_wording_version' => $current]);
        }

        $row->save();

        if ($activation) {
            $this->audit->handle('tenancy.channel_enabled', $row, ['channel' => 'whatsapp', 'wording_version' => $current]);
        } elseif ($channel === 'whatsapp' && $wasEnabled && ! $enabled) {
            $this->audit->handle('tenancy.channel_disabled', $row, ['channel' => 'whatsapp']);
        }
    }
}
