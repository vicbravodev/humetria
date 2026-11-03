<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Support\PosterPages;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The printable poster with the QR of the channel. Rotating the code makes the printed one stop working. */
final class PosterController extends Controller
{
    public function show(mixed $tenant, PosterPages $pages): Response
    {
        Gate::authorize('manage-complaint-channel');
        $poster = $pages->show();
        abort_if($poster === null, 404);

        return Inertia::render('complaints/poster', $poster);
    }
}
