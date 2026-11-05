<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Support\PosterPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** The printable poster with the link and the QR of the page of the workers. Replacing the link makes the printed one stop working. */
final class PosterController extends Controller
{
    /** With `center`, the poster of that center; one that is not in use or not the company's is the same 404. */
    public function show(Request $request, PosterPages $pages, WorkCenters $workCenters): Response
    {
        Gate::authorize('manage-disclosure');
        $id = $request->string('center')->toString();
        $center = $id === '' ? null : (Str::isUuid($id) ? $workCenters->findActive($id) : null);
        abort_if($id !== '' && $center === null, 404);

        try {
            return Inertia::render('disclosure/poster', $pages->show($center));
        } catch (ModelNotFoundException) {
            abort(404);
        }
    }
}
