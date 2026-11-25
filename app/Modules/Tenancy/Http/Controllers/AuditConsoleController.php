<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ExportAuditTrail;
use App\Modules\Tenancy\Support\AuditPages;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The audit trail for the platform's superadmin: it only reads, there is no route that changes it. */
final class AuditConsoleController extends Controller
{
    public function index(Request $request, AuditPages $pages): Response
    {
        Gate::authorize('view-audit');

        $filters = $this->filters($request, $pages);

        return Inertia::render('tenancy/audit', [
            'entries' => $pages->handle($filters),
            'counts' => $pages->counts($filters),
            'filters' => $filters,
            'company' => $pages->companyName($filters['company']),
            'families' => array_map(
                fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys(AuditPages::FAMILIES),
                array_values(AuditPages::FAMILIES),
            ),
        ]);
    }

    /** What the filters find, as a CSV to download; the export goes to the trail too. */
    public function export(Request $request, AuditPages $pages, ExportAuditTrail $export): StreamedResponse
    {
        Gate::authorize('view-audit');

        $csv = $export->handle($this->filters($request, $pages));
        $day = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, "bitacora-{$day}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{q: string, kind: string, family: string, company: string, from: string, to: string} */
    private function filters(Request $request, AuditPages $pages): array
    {
        return $pages->filters(
            $request->string('q')->toString(),
            $request->string('kind')->toString(),
            $request->string('family')->toString(),
            $request->string('company')->toString(),
            $request->string('from')->toString(),
            $request->string('to')->toString(),
        );
    }

    /**
     * The companies the trail can be narrowed to, found on the server as the filter is typed.
     *
     * @return list<array{value: string, label: string}>
     */
    public function companies(Request $request, AuditPages $pages): array
    {
        Gate::authorize('view-audit');

        return $pages->companies($request->string('q')->trim()->limit(100, '')->toString());
    }
}
