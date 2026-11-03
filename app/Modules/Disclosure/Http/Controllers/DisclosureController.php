<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Support\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start of «Política y difusión»: the board of the company, center by center. Owner, RH and the viewer read it; it shows
 * counts and states, never a person. The Comisión de seguridad e higiene and the handlers of complaints have no part in it.
 */
final class DisclosureController extends Controller
{
    public function index(Request $request, Board $board): Response
    {
        Gate::authorize('view-disclosure');

        return Inertia::render('disclosure/index', [...$board->page(), 'can' => ['manage' => $request->user()?->can('manage-disclosure') ?? false]]);
    }
}
