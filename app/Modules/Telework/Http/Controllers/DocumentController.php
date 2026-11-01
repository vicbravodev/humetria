<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\PublishDocument;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Http\Requests\DocumentRequest;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\AckProgress;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The company's NOM-037 documents (PDF), by work center. Owner, hr and viewer read every center's; a committee user only
 * those it is assigned to (any other is a 404, as is another company's). Only owner and hr publish. Nothing is deleted
 * or replaced: a new upload is a new version.
 */
final class DocumentController extends Controller
{
    public function __construct(private readonly VisibleCenters $visible, private readonly WorkCenters $workCenters) {}

    public function index(Request $request, AckProgress $acknowledgements): Response
    {
        Gate::authorize('view-telework');
        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center;
        }

        $names = array_map(fn (WorkCenterSummary $center): string => $center->name, $centers);

        $only = $request->string('center')->toString();
        $limit = $this->limitedCenters($request);
        $selected = Str::isUuid($only) && ($limit === null || in_array($only, $limit, true)) ? ($centers[$only] ?? null) : null;
        $history = $request->string('versions')->toString() === 'all';

        $page = TeleworkDocument::query()
            ->when($limit !== null, fn (Builder $query) => $query->whereIn('work_center_id', $limit))
            ->when($selected, fn (Builder $query, WorkCenterSummary $center) => $query->where('work_center_id', $center->id))
            ->when(! $history, fn (Builder $query) => $query->inForce())
            ->orderByDesc('created_at')
            ->orderByDesc('version')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        // The items of a page are already a list.
        $progress = $acknowledgements->forDocuments(array_values($page->items())); // @pest-mutate-ignore: UnwrapArrayValues

        return Inertia::render('telework/documents', [
            'documents' => $page->through(fn (TeleworkDocument $document): array => [
                'id' => $document->id,
                'kind' => $document->kind->value,
                // $names has every center of the company, archived ones too.
                'workCenter' => ['id' => $document->work_center_id, 'name' => $names[$document->work_center_id]],
                'version' => $document->version,
                'publishedOn' => $document->created_at->setTimezone(config()->string('app.display_timezone'))->toDateString(),
                'coveredItems' => $document->covered_items,
                'missingItems' => array_values(array_diff($document->kind->coverableItems(), $document->covered_items)),
                'acknowledgements' => $document->kind->needsAcknowledgement() ? $progress[$document->id] : null,
            ]),
            'filters' => [
                'center' => $selected === null ? null : ['value' => $selected->id, 'label' => $selected->name],
                'versions' => $history ? 'all' : 'latest',
            ],
            'kinds' => array_map(fn (DocumentKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
                'numeral' => $kind->numeral(),
                // An array_map() over two arrays numbers its result anew: the keys of the labels are never read.
                'items' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($kind->items()), array_values($kind->items())), // @pest-mutate-ignore: UnwrapArrayValues
            ], DocumentKind::cases()),
            'canManage' => Gate::allows('manage-telework'),
        ]);
    }

    public function store(DocumentRequest $request, mixed $tenant, PublishDocument $publish): RedirectResponse
    {
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall
        // PublishDocument only iterates the ids: their keys are never read.
        $centers = $request->allCenters()
            ? array_values( // @pest-mutate-ignore: UnwrapArrayValues
                array_map(fn (WorkCenterSummary $center): string => $center->id, array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)))
            : $request->workCenterIds();

        $publish->handle($request->kind(), $request->documentFile(), $centers, $request->coveredItems(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento publicado.']);

        return redirect()->route('tenant.telework.documents.index', $tenant);
    }

    public function file(Request $request, mixed $tenant, string $document, FileStore $files): StreamedResponse
    {
        Gate::authorize('view-telework');
        $found = Str::isUuid($document) ? TeleworkDocument::query()->find($document) : null;
        abort_if($found === null || ! in_array($found->work_center_id, $this->limitedCenters($request) ?? [$found->work_center_id], true), 404);

        $response = $files->stream($found->path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * The only work centers the user may see, or null when it sees every center of the company. Worked out once per request.
     *
     * @return list<string>|null
     */
    private function limitedCenters(Request $request): ?array
    {
        if (! $request->attributes->has('telework.limited_centers')) {
            $user = $request->user();
            $request->attributes->set('telework.limited_centers', $user === null ? [] : $this->visible->limitedTo($user->id));
        }

        $limited = $request->attributes->get('telework.limited_centers');

        // What was stored above is the list<string> limitedTo() gives: the filter and the renumbering only narrow the type.
        return is_array($limited) ? array_values(array_filter($limited, is_string(...))) : null; // @pest-mutate-ignore: UnwrapArrayFilter,UnwrapArrayValues
    }
}
