<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ChannelSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Changes the procedure, the deadlines and the retention of the company's channel; its code and its state stay. */
final readonly class SaveChannelSettings
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(ChannelSettings $settings): ComplaintChannel
    {
        $channel = DB::transaction(function () use ($settings): ComplaintChannel {
            $channel = ComplaintChannel::query()->lockForUpdate()->firstOrFail();
            $channel->update($settings->attributes());
            $this->audit->handle('complaint_channel.settings_updated', $channel);

            return $channel;
        });

        Log::info('complaints.settings_updated', ['channel_id' => $channel->id]);

        return $channel;
    }
}
