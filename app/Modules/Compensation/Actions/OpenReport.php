<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\ReportBuilder;
use Illuminate\Support\Facades\Log;

final readonly class OpenReport
{
    public function __construct(private ReportBuilder $report, private RecordAudit $audit) {}

    /**
     * The report of a scenario for its page. Seeing the salaries of the company is sensitive, so each view is logged and goes
     * to the audit trail with the scenario and nothing of what it shows.
     *
     * @return array<string, mixed>
     */
    public function handle(Scenario $scenario): array
    {
        $report = $this->report->build($scenario);

        Log::info('compensation.report_viewed', ['scenario_id' => $scenario->id]);
        $this->audit->handle('compensation.report_viewed', null, ['scenario_id' => $scenario->id]);

        return $report;
    }
}
