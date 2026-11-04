<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\OpenWorkersPage;
use App\Modules\Disclosure\Exceptions\PageUnavailable;
use App\Modules\Disclosure\Support\OpenedPage;
use App\Modules\Disclosure\Support\WorkersPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The public page of the workers, opened with the link RH prints (criterio C-55): the centers of the company and, for each,
 * what it published. It reads only; it keeps nothing of who opens it. Any reason it does not open (a link that does not work, a
 * center that is not the company's) is the same 404 page, which names nobody.
 */
final class WorkersPageController extends Controller
{
    public function __construct(private readonly OpenWorkersPage $open, private readonly WorkersPages $pages, private readonly WorkCenters $workCenters) {}

    public function show(Request $request, string $token): Response|RedirectResponse|SymfonyResponse
    {
        $opened = $this->opened($token);

        if ($opened === null) {
            return $this->inactive($request);
        }

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $centers = $this->pages->centers($search);

        // A company with one center has nothing to choose: its page is that center's.
        if ($search === '' && $centers['centers']->total() === 1) {
            return redirect()->route('workers.center', [$token, $centers['centers']->items()[0]['id']]);
        }

        return Inertia::render('disclosure-public/centers', ['token' => $token, 'company' => $opened->company, ...$centers]);
    }

    public function center(Request $request, string $token, string $workCenter): Response|SymfonyResponse
    {
        $opened = $this->opened($token);
        $center = $opened === null || ! Str::isUuid($workCenter) ? null : $this->workCenters->findActive($workCenter);

        if ($opened === null || $center === null) {
            return $this->inactive($request);
        }

        return Inertia::render('disclosure-public/center', ['token' => $token, 'company' => $opened->company, ...$this->pages->center($center)]);
    }

    private function opened(string $token): ?OpenedPage
    {
        try {
            return $this->open->handle($token);
        } catch (PageUnavailable) {
            return null;
        }
    }

    /** The page that says the link is not active, as a 404 and with nothing that tells one reason from another. */
    private function inactive(Request $request): HttpResponse|SymfonyResponse
    {
        return Inertia::render('disclosure-public/inactive')->toResponse($request)->setStatusCode(404);
    }
}
