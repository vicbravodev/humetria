<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Models\SeatReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH closes a seat report by writing how it was resolved. The report stays as evidence (criterio C-17).
 */
final readonly class ResolveSeatReport
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ValidationException when the report was already resolved.
     */
    public function handle(SeatReport $report, string $resolution): SeatReport
    {
        return DB::transaction(function () use ($report, $resolution): SeatReport {
            $locked = SeatReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReportStatus::Resolved) {
                throw ValidationException::withMessages(['status' => 'Este reporte ya está resuelto.']);
            }

            $locked->update(['status' => ReportStatus::Resolved, 'resolution' => $resolution, 'resolved_at' => now()]);

            $record = ['report_id' => $locked->id, 'work_center_id' => $locked->work_center_id];
            $this->audit->handle('seating.seat_report_resolved', $locked, $record);
            Log::info('seating.seat_report_resolved', $record);

            return $locked;
        });
    }
}
