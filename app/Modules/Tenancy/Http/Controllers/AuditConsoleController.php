<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\AuditPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The audit trail for the platform's superadmin: it only reads, there is no route that changes it. */
final class AuditConsoleController extends Controller
{
    public function index(Request $request, AuditPages $pages): Response
    {
        Gate::authorize('view-audit');

        $filters = $pages->filters(
            $request->string('q')->toString(),
            $request->string('family')->toString(),
            $request->string('company')->toString(),
            $request->string('from')->toString(),
            $request->string('to')->toString(),
        );

        return Inertia::render('tenancy/audit', [
            'entries' => $pages->handle($filters),
            'filters' => $filters,
            'company' => $pages->companyName($filters['company']),
            'families' => array_map(
                fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys(AuditPages::FAMILIES),
                array_values(AuditPages::FAMILIES),
            ),
        ]);
    }
}
