<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Closes a request with the reason the team gives; the reason, free text, is never logged or audited. */
final readonly class DiscardDemoRequest
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(DemoRequest $request, string $reason): void
    {
        DB::transaction(function () use ($request, $reason): void {
            $request->forceFill([
                'status' => DemoStatus::Discarded,
                'discard_reason' => $reason,
                'handled_at' => $request->handled_at ?? now(),
            ])->save();

            Log::info('demo.discarded', ['demo_request_id' => $request->id]);
            $this->recordAudit->handle('demo.discarded', $request, []);
        });
    }
}
