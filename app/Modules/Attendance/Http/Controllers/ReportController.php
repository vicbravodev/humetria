<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Http\Requests\ReportRequest;
use App\Modules\Attendance\Support\AuthorityCsv;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The report for the authority (LFT art. 132-XXXIV): owner and hr see it and download it; a viewer gets a 403, a center that
 * is not one in use of the company a 404, as all of it for a union.
 */
final readonly class ReportController
{
    public function __construct(private WorkCenters $workCenters) {}

    /** The CSV, streamed row by row: never held whole in memory, never cached. */
    public function export(ReportRequest $request, BuildAuthorityReport $report): StreamedResponse
    {
        $center = $this->workCenters->findActive($request->centerId()) ?? abort(404);
        $from = $request->from();
        $to = $request->to();
        $slug = Str::slug($center->name) ?: 'centro';

        return response()->streamDownload(function () use ($report, $center, $from, $to): void {
            $out = fopen('php://output', 'wb');

            if ($out !== false) {
                AuthorityCsv::write($report->handle($center->id, $from, $to), $out);
                fclose($out);
            }
        }, "registro-jornada-{$slug}-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
