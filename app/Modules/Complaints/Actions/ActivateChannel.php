<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Events\ComplaintChannelPublished;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ChannelSettings;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\RandomCode;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Opens the channel to the company's people: the first activation draws the access code, fixes the day it was published and
 * designates the person who activates it as a handler for the whole company. Activating an open channel changes nothing.
 */
final readonly class ActivateChannel
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit, private DesignateHandler $designate) {}

    public function handle(ChannelSettings $settings, int $activatedBy): ComplaintChannel
    {
        $channel = DB::transaction(function () use ($settings, $activatedBy): ComplaintChannel {
            $channel = ComplaintChannel::query()->lockForUpdate()->first()
                ?? new ComplaintChannel(['tenant_id' => $this->tenant->id(), 'access_code' => RandomCode::make(16)]);

            if ($channel->enabled_at !== null) {
                return $channel;
            }

            $channel->fill([...$settings->attributes(), 'enabled_at' => now(), 'published_on' => Dates::today(), 'paused_at' => null])->save();
            $this->audit->handle('complaint_channel.activated', $channel);
            // Whoever opens the channel handles it to begin with, so it serves from the first day.
            $this->designate->handle($activatedBy, null, $activatedBy);

            return $channel;
        });

        if ($channel->wasChanged() || $channel->wasRecentlyCreated) {
            Log::info('complaints.channel_activated', ['channel_id' => $channel->id]);
            event(new ComplaintChannelPublished($this->tenant->id(), $channel->published_on ?? Dates::today()));
        }

        return $channel;
    }
}
