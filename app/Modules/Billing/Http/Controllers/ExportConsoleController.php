<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\ExportReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportConsoleController extends Controller
{
    private const array NAMES = ['portfolio' => 'cartera', 'balances' => 'saldos', 'renewals' => 'renovaciones'];

    public function show(string $report, ExportReport $export): StreamedResponse
    {
        Gate::authorize('view-billing');

        $csv = $export->handle($report);
        $day = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, self::NAMES[$report].'-'.$day.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
