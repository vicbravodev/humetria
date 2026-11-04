<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Complaints\Actions\ViewCase;
use App\Modules\Complaints\Support\CaseAccess;
use App\Modules\Complaints\Support\CasePages;
use App\Modules\Complaints\Support\InboxPages;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cases a handler works on. Being able to handle complaints opens none: a case opens only to a current assignment, and
 * to whoever has none it answers 404, as it would for a case that does not exist.
 */
final class ComplaintController extends Controller
{
    public function index(Request $request, mixed $tenant, InboxPages $pages, #[CurrentUser] User $user): Response
    {
        Gate::authorize('handle-complaints');

        return Inertia::render('complaints/index', $pages->index($user->id, $request));
    }

    public function show(mixed $tenant, string $complaint, CaseAccess $access, CasePages $pages, ViewCase $view, #[CurrentUser] User $user): Response
    {
        Gate::authorize('handle-complaints');
        $case = $access->find($user->id, $complaint);
        abort_if($case === null, 404);

        $view->handle($case);

        return Inertia::render('complaints/show', $pages->show($case));
    }
}
