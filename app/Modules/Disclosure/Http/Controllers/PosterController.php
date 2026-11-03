<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Support\PosterPages;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The printable poster with the link and the QR of the page of the workers. Replacing the link makes the printed one stop working. */
final class PosterController extends Controller
{
    public function show(PosterPages $pages): Response
    {
        Gate::authorize('manage-disclosure');

        try {
            return Inertia::render('disclosure/poster', $pages->show());
        } catch (ModelNotFoundException) {
            abort(404);
        }
    }
}
