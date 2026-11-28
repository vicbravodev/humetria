<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves a request along and keeps the team's note on it, who of the staff owns it and its next step. The first day someone
 * handled it is kept, and cleared if it goes back to new. A converted request keeps its status: only the rest changes.
 * The note and the next step are never logged or audited; a new owner is.
 */
final readonly class UpdateDemoRequest
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(DemoRequest $request, DemoStatus $status, ?string $note, ?int $ownerId = null, ?string $nextStep = null): void
    {
        DB::transaction(function () use ($request, $status, $note, $ownerId, $nextStep): void {
            $status = $request->status === DemoStatus::Converted ? DemoStatus::Converted : $status;
            $reassigned = $request->owner_user_id !== $ownerId;

            $request->forceFill([
                'status' => $status,
                'note' => $note,
                'owner_user_id' => $ownerId,
                'next_step' => $nextStep,
                'handled_at' => $status === DemoStatus::New ? null : ($request->handled_at ?? now()),
            ])->save();

            Log::info('demo.updated', ['demo_request_id' => $request->id, 'status' => $status->value]);
            $this->recordAudit->handle('demo.updated', $request, ['status' => $status->value]);

            if ($reassigned) {
                Log::info('demo.assigned', ['demo_request_id' => $request->id, 'owner_user_id' => $ownerId]);
                $this->recordAudit->handle('demo.assigned', $request, ['owner_user_id' => $ownerId]);
            }
        });
    }
}
