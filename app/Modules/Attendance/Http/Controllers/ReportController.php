<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\AuditReport;
use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Http\Requests\ReportRequest;
use App\Modules\Attendance\Support\AuthorityCsv;
use App\Modules\Attendance\Support\PanelScope;
use App\Modules\Attendance\Support\ReportPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The report for the authority (LFT art. 132-XXXIV): owner and hr see it and download it; a viewer gets a 403, a center that
 * is not one in use of the company a 404, as all of it for a union.
 */
final readonly class ReportController
{
    public function __construct(private WorkCenters $workCenters) {}

    /**
     * The page; a center's report on its first page goes to the audit trail (AuditReport). A period too long for the screen
     * offers only the CSV: it reads no marks, so it is no view (the download is audited on its own).
     */
    public function show(ReportRequest $request, ReportPages $pages, AuditReport $audit): Response
    {
        [$from, $to] = $request->hasPeriod() ? [$request->from(), $request->to()] : ReportPages::lastWeek();
        $center = PanelScope::center($this->workCenters, $request->centerId());
        $onlyDownload = $request->onlyDownload();
        $page = $pages->show($request->tenantSlug(), $center, $from, $to, $request->string('q')->trim()->limit(100, '')->toString(), $onlyDownload ? $request->days() : null, $request->show());

        // Its first page with no search or filter: the next pages and each search or filter of the same report are not new views.
        if ($center !== null && ! $onlyDownload && $request->integer('page', 1) <= 1 && ! $request->filled('q') && $request->show() === 'all') {
            $audit->handle('view', $center->id, $from, $to, $pages->days($page));
        }

        return Inertia::render('attendance/report', $page);
    }

    /** The CSV, streamed row by row: never held whole in memory, never cached. */
    public function export(ReportRequest $request, BuildAuthorityReport $report, AuditReport $audit): StreamedResponse
    {
        $center = PanelScope::center($this->workCenters, $request->centerId()) ?? abort(404);
        $from = $request->from();
        $to = $request->to();
        $slug = Str::slug($center->name) ?: 'centro';

        // Audited before the first row, so a download cut off halfway is on the trail; the rows go to the log at the end. A
        // client that leaves does not stop the stream midway, nor does the time limit.
        return response()->streamDownload(function () use ($report, $audit, $center, $from, $to): void {
            ignore_user_abort(true);
            set_time_limit(0);
            $audit->handle('export', $center->id, $from, $to);
            $out = fopen('php://output', 'wb');

            if ($out !== false) {
                $rows = AuthorityCsv::write($report->handle($center->id, $from, $to), $out);
                fclose($out);
                $audit->completed($center->id, $from, $to, $rows);
            }
        }, "registro-jornada-{$slug}-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
