<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Events\ComplaintChannelPaused;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Stops the channel from taking complaints for now; what it already holds stays and is still handled. */
final readonly class PauseChannel
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(): void
    {
        $channel = DB::transaction(function (): ?ComplaintChannel {
            $channel = ComplaintChannel::query()->lockForUpdate()->firstOrFail();

            if (! $channel->isOpen()) {
                return null;
            }

            $channel->update(['paused_at' => now()]);
            $this->audit->handle('complaint_channel.paused', $channel);

            return $channel;
        });

        if ($channel !== null) {
            Log::info('complaints.channel_paused', ['channel_id' => $channel->id]);
            event(new ComplaintChannelPaused($this->tenant->id()));
        }
    }
}
