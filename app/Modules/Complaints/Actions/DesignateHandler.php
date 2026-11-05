<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\HandlerNotices;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Designates a person to handle the company's complaints, for the whole company or one work center, and tells the other
 * handlers and, apart, the person designated. Designating again someone who already holds that scope changes nothing.
 */
final readonly class DesignateHandler
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit, private HandlerNotices $notices) {}

    public function handle(int $userId, ?string $workCenterId, int $designatedBy): ComplaintHandler
    {
        $open = ComplaintHandler::query()->whereNull('ended_at')->where('user_id', $userId)->where('work_center_id', $workCenterId)->first();

        if ($open !== null) {
            return $open;
        }

        $handler = DB::transaction(function () use ($userId, $workCenterId, $designatedBy): ComplaintHandler {
            $handler = ComplaintHandler::query()->create([
                'tenant_id' => $this->tenant->id(),
                'user_id' => $userId,
                'work_center_id' => $workCenterId,
                'designated_by' => $designatedBy,
                'designated_at' => now(),
            ]);
            $this->audit->handle('complaint_handler.designated', $handler);

            return $handler;
        });

        Log::info('complaints.handler_designated', ['handler_id' => $handler->id]);
        $this->notices->send($userId, designated: true);

        if ($userId !== $designatedBy) {
            $this->notices->welcome($userId, $workCenterId);
        }

        return $handler;
    }
}
