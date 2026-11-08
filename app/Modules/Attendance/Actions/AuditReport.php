<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Who saw or took the report for the authority (criterio 6.1, a sensitive action): to the audit trail and the log as
 * `attendance.report_exported`, with the center's id, the period, the rows and how (`view` on the page, `export` as CSV).
 * Never a name, a reason or a position. The page writes its first page only (a visit to the center and period, or a
 * search): turning to the next pages of the same report writes nothing more.
 */
final readonly class AuditReport
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @param  'view'|'export'  $action
     */
    public function handle(string $action, string $workCenterId, CarbonImmutable $from, CarbonImmutable $to, int $rows): void
    {
        $metadata = [
            'action' => $action,
            'work_center_id' => $workCenterId,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'format' => $action === 'export' ? 'csv' : 'page',
        ];

        $this->audit->handle('attendance.report_exported', metadata: $metadata);
        Log::info('attendance.report_exported', $metadata);
    }
}
