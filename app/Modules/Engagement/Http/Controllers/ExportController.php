<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Requests\ResultsRequest;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\ResultsCsv;
use App\Modules\Engagement\Support\Results\SurveyResults;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads what the results screen shows, as a CSV, under the very same minimums and one cut at a time. Every download is
 * audited with which survey and cut.
 */
final class ExportController extends Controller
{
    public function __construct(private readonly RecordAudit $audit, private readonly ResultsCsv $csv) {}

    public function show(ResultsRequest $request, mixed $tenant, string $survey, SurveyKind $kind): StreamedResponse
    {
        $survey = Survey::query()->findOrFail($survey);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('view', $survey);

        $results = SurveyResults::for($survey);
        abort_unless($results->available(), 404);

        $cut = $request->cut();
        $rows = $this->csv->rows($results, $cut);
        Log::info('engagement.results_exported', ['survey_id' => $survey->id, 'cut' => $cut?->value]);
        $this->audit->handle('engagement.results_exported', $survey, ['cut' => $cut?->value]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // The byte order mark lets Excel read the accents.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, "clima-{$survey->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
