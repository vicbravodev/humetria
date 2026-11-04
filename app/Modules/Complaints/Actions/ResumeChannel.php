<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Models\ComplaintChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Opens a paused channel again, with the same code and the same day of publication. */
final readonly class ResumeChannel
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(): void
    {
        $channel = DB::transaction(function (): ?ComplaintChannel {
            $channel = ComplaintChannel::query()->lockForUpdate()->firstOrFail();

            if ($channel->status() !== ChannelStatus::Paused) {
                return null;
            }

            $channel->update(['paused_at' => null]);
            $this->audit->handle('complaint_channel.resumed', $channel);

            return $channel;
        });

        if ($channel !== null) {
            Log::info('complaints.channel_resumed', ['channel_id' => $channel->id]);
        }
    }
}
