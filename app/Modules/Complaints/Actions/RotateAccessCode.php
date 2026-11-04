<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Events\ComplaintChannelCodeRotated;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\RandomCode;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Changes the access code: the printed QR and every link given out before stop working. */
final readonly class RotateAccessCode
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(): ComplaintChannel
    {
        $channel = DB::transaction(function (): ComplaintChannel {
            $channel = ComplaintChannel::query()->lockForUpdate()->firstOrFail();
            $channel->update(['access_code' => RandomCode::make(16), 'access_code_rotated_on' => Dates::today()]);
            $this->audit->handle('complaint_channel.code_rotated', $channel);

            return $channel;
        });

        Log::info('complaints.code_rotated', ['channel_id' => $channel->id]);
        event(new ComplaintChannelCodeRotated($this->tenant->id()));

        return $channel;
    }
}
