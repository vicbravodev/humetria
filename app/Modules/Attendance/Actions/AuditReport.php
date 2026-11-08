<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Who saw or took the report for the authority (criterio 6.1, a sensitive action): to the audit trail and the log as
 * `attendance.report_exported`, with the center's id, the period, the rows and how (`view` on the page, `export` as CSV).
 * Never a name, a reason or a position. The page writes its first page with no search only: turning to the next pages
 * of the same report, or searching in it, writes nothing more. A download is written before its first row and its row
 * count logged at its end.
 */
final readonly class AuditReport
{
    public function __construct(private RecordAudit $audit) {}

    /** A view of the page, with the person-days it counts. */
    public function viewed(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to, int $rows): void
    {
        $this->record([...self::ids('view', $workCenterId, $from, $to), 'rows' => $rows, 'format' => 'page']);
    }

    /**
     * A download, written before its first row: a download cut off halfway is still on the trail. How many rows went out
     * is only known at its end, so it goes to the log then (completed()), not to a second audit entry.
     */
    public function exporting(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->record([...self::ids('export', $workCenterId, $from, $to), 'format' => 'csv']);
    }

    /** The download's end: the same ids with the rows that went out. */
    public function completed(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to, int $rows): void
    {
        Log::info('attendance.report_exported', [...self::ids('export', $workCenterId, $from, $to), 'format' => 'csv', 'rows' => $rows, 'completed' => true]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(array $metadata): void
    {
        $this->audit->handle('attendance.report_exported', metadata: $metadata);
        Log::info('attendance.report_exported', $metadata);
    }

    /**
     * @return array{action: string, work_center_id: string, from: string, to: string}
     */
    private static function ids(string $action, string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return ['action' => $action, 'work_center_id' => $workCenterId, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
    }
}
