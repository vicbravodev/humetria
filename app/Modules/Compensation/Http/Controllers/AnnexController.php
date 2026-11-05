<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ExportPersonAnnex;
use App\Modules\Compensation\Models\Scenario;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The annex of a scenario by person, for whoever manages compensation: never in a shared link. */
final class AnnexController extends Controller
{
    public function __invoke(mixed $tenant, string $scenario, ExportPersonAnnex $export): StreamedResponse
    {
        Gate::authorize('manage-compensation');

        $rows = $export->handle(Scenario::query()->findOrFail($scenario));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // The byte order mark lets Excel read the accents as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, 'anexo-por-persona.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
