<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Listeners;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Events\MemberRemoved;
use Illuminate\Support\Facades\Log;

/** A person who lost their access to the company stops handling its complaints. */
final readonly class EndHandlerOnMemberRemoval
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(MemberRemoved $event): void
    {
        $open = ComplaintHandler::query()->withoutGlobalScopes()->where('tenant_id', $event->tenantId)->where('user_id', $event->userId)->whereNull('ended_at')->get();

        foreach ($open as $handler) {
            $handler->update(['ended_at' => now()]);
            $this->audit->handle('complaint_handler.ended', $handler, ['reason' => 'member_removed']);
            Log::info('complaints.handler_ended', ['handler_id' => $handler->id, 'reason' => 'member_removed']);
        }
    }
}
