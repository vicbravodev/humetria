<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Sets the day of a request's demo and moves it to «Demo agendada»; counts as handling it. */
final readonly class ScheduleDemo
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(DemoRequest $request, CarbonImmutable $at): void
    {
        DB::transaction(function () use ($request, $at): void {
            $request->forceFill([
                'status' => DemoStatus::Scheduled,
                'scheduled_at' => $at,
                'handled_at' => $request->handled_at ?? now(),
            ])->save();

            Log::info('demo.scheduled', ['demo_request_id' => $request->id]);
            $this->recordAudit->handle('demo.scheduled', $request, ['scheduled_at' => $at->toIso8601String()]);
        });
    }
}
