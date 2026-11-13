<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves a request along and keeps the team's note on it. The first day someone handled it is kept, and cleared if it goes
 * back to new. A converted request keeps its status: only its note changes. The note is never logged or audited.
 */
final readonly class UpdateDemoRequest
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(DemoRequest $request, DemoStatus $status, ?string $note): void
    {
        DB::transaction(function () use ($request, $status, $note): void {
            $status = $request->status === DemoStatus::Converted ? DemoStatus::Converted : $status;

            $request->forceFill([
                'status' => $status,
                'note' => $note,
                'handled_at' => $status === DemoStatus::New ? null : ($request->handled_at ?? now()),
            ])->save();

            Log::info('demo.updated', ['demo_request_id' => $request->id, 'status' => $status->value]);
            $this->recordAudit->handle('demo.updated', $request, ['status' => $status->value]);
        });
    }
}
