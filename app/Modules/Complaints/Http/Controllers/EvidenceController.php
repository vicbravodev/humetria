<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Actions\RecordEvidenceExport;
use App\Modules\Complaints\Actions\RecordEvidenceView;
use App\Modules\Complaints\Support\EvidencePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The printable evidence of the mechanism for an inspection (NOM-035 10.2): how the channel is set up, never a case. */
final class EvidenceController extends Controller
{
    public function show(mixed $tenant, EvidencePages $pages, RecordEvidenceView $view): Response
    {
        Gate::authorize('manage-complaint-channel');
        $view->handle();

        return Inertia::render('complaints/evidence', $pages->show());
    }

    public function export(mixed $tenant, RecordEvidenceExport $export): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $export->handle();

        return to_route('tenant.complaints.evidence', $tenant);
    }
}
