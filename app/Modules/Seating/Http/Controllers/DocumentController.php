<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\PublishDocument;
use App\Modules\Seating\Actions\RecordAssessmentInMinutes;
use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Http\Requests\DocumentRequest;
use App\Modules\Seating\Http\Requests\RecordMinutesRequest;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Support\AssessmentAccess;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\DocumentPages;
use App\Modules\Seating\Support\SeatingFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The documents RH keeps for the inspection (PDF only, on the private disk), and the link between a signed assessment and
 * the minutes of the Comisión where it was recorded. Nothing is deleted (criterio C-17).
 */
final class DocumentController extends Controller
{
    public function index(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, DocumentPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/documents', $pages->index($tenant, $center));
    }

    public function store(DocumentRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, PublishDocument $publish): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $publish->handle($center->id, $request->kind(), $request->title(), $request->string('document_on')->toString(), $request->incisos(), $request->filePath(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento guardado.']);

        return redirect()->route('tenant.seating.documents.index', [$tenant, $center->id]);
    }

    public function file(Request $request, mixed $tenant, string $document, CenterAccess $access, SeatingFiles $files): StreamedResponse
    {
        abort_unless(Gate::allows('manage-seating') || Gate::allows('assess-seating'), 403);
        $found = SeatingDocument::query()->find($document);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);

        $response = $files->stream($found->file_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * The minutes of the center that match what was typed, for the field that picks them.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(Request $request, mixed $tenant, string $workCenter, CenterAccess $access): array
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return array_values(SeatingDocument::query()->where('work_center_id', $center->id)->where('kind', DocumentKind::CommissionMinutes)
            ->matching(['title'], $request->string('q')->trim()->limit(100, '')->toString())->orderByDesc('document_on')->orderBy('title')->limit(20)->get(['id', 'title'])
            ->map(fn (SeatingDocument $document): array => ['value' => $document->id, 'label' => $document->title])->all());
    }

    public function record(RecordMinutesRequest $request, mixed $tenant, string $assessment, CenterAccess $access, AssessmentAccess $assessors, RecordAssessmentInMinutes $record): RedirectResponse
    {
        $found = RiskAssessment::query()->find($assessment);
        abort_if($found === null, 404);
        $access->find($request, $found->work_center_id);
        abort_unless($assessors->canAssess($access->userId($request), $found->work_center_id), 403);

        $record->handle($found, SeatingDocument::query()->find($request->string('document_id')->toString()));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La evaluación quedó asentada en el acta.']);

        return redirect()->route('tenant.seating.assessments.show', [$tenant, $found->id]);
    }
}
