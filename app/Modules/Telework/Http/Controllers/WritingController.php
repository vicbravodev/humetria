<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\PublishWriting;
use App\Modules\Telework\Actions\SaveWriting;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Http\Requests\WritingRequest;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Telework\Support\Writing\WrittenTemplate;
use App\Modules\Telework\Support\WritingPage;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writing a NOM-037 document with questions (boards V2-Politica and V3-Doc-Riesgos; decisión A3), for whoever manages
 * NOM-037. A kind that is not written with questions is a 404.
 */
final class WritingController extends Controller
{
    public function __construct(private readonly Writer $writer, private readonly WorkCenters $workCenters) {}

    /** The six supporting documents (board V2-N037-Docs): everyone who reads NOM-037 sees them; whoever manages it writes them. */
    public function index(WritingPage $page): Response
    {
        Gate::authorize('view-telework');

        return Inertia::render('telework/supporting-documents', [...$page->supporting(), 'canManage' => Gate::allows('manage-telework')]);
    }

    public function show(Request $request, mixed $tenant, string $kind, WritingPage $page): Response
    {
        Gate::authorize('manage-telework');
        [$document, $template] = $this->written($kind);

        return Inertia::render('telework/write', $page->props($template, $document, $this->back($request, $tenant, $document)));
    }

    public function publish(Request $request, mixed $tenant, string $kind, PublishWriting $publish): RedirectResponse
    {
        Gate::authorize('manage-telework');
        [$document, $template] = $this->written($kind);
        $user = $request->user();
        abort_if($user === null, 403);
        $publish->handle($template, $document, $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$template->name()} publicada."]);

        return redirect($this->back($request, $tenant, $document)['href']);
    }

    public function update(WritingRequest $request, mixed $tenant, string $kind, SaveWriting $save): RedirectResponse
    {
        [$document, $template] = $this->written($kind);
        $user = $request->user();
        abort_if($user === null, 403);
        $save->handle($template, $document, $request->answers(), $request->step(), $user->id, $request->edit());

        return back(fallback: route('tenant.telework.writing.show', [$tenant, $document->value]));
    }

    /**
     * Where «Guardar y volver» leads: the route of the center it came from; else the policy to the centers and a supporting
     * document to its list.
     *
     * @return array{href: string, label: string, center: string|null}
     */
    private function back(Request $request, mixed $tenant, DocumentKind $kind): array
    {
        $center = $request->string('center')->toString();
        $from = Str::isUuid($center) ? $this->workCenters->findActive($center) : null;

        return match (true) {
            $from !== null => ['href' => route('tenant.telework.route', [$tenant, $from->id]), 'label' => 'Guardar y volver a la ruta', 'center' => $from->id],
            $kind === DocumentKind::Policy => ['href' => route('tenant.telework.index', $tenant), 'label' => 'Guardar y volver', 'center' => null],
            default => ['href' => route('tenant.telework.supporting.index', $tenant), 'label' => 'Guardar y volver', 'center' => null],
        };
    }

    /** @return array{DocumentKind, WrittenTemplate} */
    private function written(string $kind): array
    {
        $document = DocumentKind::tryFrom($kind);
        $template = $document === null ? null : $this->writer->template($document);
        abort_if($document === null || $template === null, 404);

        return [$document, $template];
    }
}
