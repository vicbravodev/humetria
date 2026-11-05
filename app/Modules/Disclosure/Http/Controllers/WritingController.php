<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\SaveWriting;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Http\Requests\WritingRequest;
use App\Modules\Disclosure\Support\Writing\Templates;
use App\Modules\Disclosure\Support\WritingPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Support\Writing\WrittenTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writing the policy of prevention or a block of 5.7 with questions (boards V2-N035-Politica and V4-Difusion-Contenidos;
 * decisión A3), for whoever manages the disclosure. A kind that is not written with questions is a 404.
 */
final class WritingController extends Controller
{
    public function __construct(private readonly Templates $templates, private readonly WorkCenters $workCenters) {}

    public function show(Request $request, mixed $tenant, string $kind, WritingPages $pages): Response
    {
        Gate::authorize('manage-disclosure');
        [$document, $template] = $this->written($kind);
        $page = $document === DocumentKind::Policy ? 'disclosure/policy-write' : 'disclosure/content-write';

        return Inertia::render($page, $pages->props($template, $document, $this->back($request, $tenant, $document)));
    }

    public function update(WritingRequest $request, mixed $tenant, string $kind, SaveWriting $save): RedirectResponse
    {
        [$document, $template] = $this->written($kind);
        $save->handle($template, $document, $request->answers(), $request->step(), $request->userId(), $request->edit());

        return back(fallback: route('tenant.disclosure.write', [$tenant, $document->value]));
    }

    /**
     * Where «Guardar y volver» leads: the NOM-035 route of the center it came from; else the policy, or the disclosure for a
     * block of 5.7.
     *
     * @return array{href: string, label: string, center: string|null}
     */
    private function back(Request $request, mixed $tenant, DocumentKind $kind): array
    {
        $center = $request->string('center')->toString();
        $from = Str::isUuid($center) ? $this->workCenters->findActive($center) : null;

        return match (true) {
            $from !== null => ['href' => route('tenant.nom035.route', [$tenant, $from->id]), 'label' => 'Guardar y volver a la ruta', 'center' => $from->id],
            $kind === DocumentKind::Policy => ['href' => route('tenant.disclosure.policy', $tenant), 'label' => 'Guardar y volver', 'center' => null],
            default => ['href' => route('tenant.disclosure.index', $tenant), 'label' => 'Guardar y volver', 'center' => null],
        };
    }

    /** @return array{DocumentKind, WrittenTemplate} */
    private function written(string $kind): array
    {
        $document = DocumentKind::tryFrom($kind);
        $template = $document === null ? null : $this->templates->template($document);
        abort_if($document === null || $template === null, 404);

        return [$document, $template];
    }
}
