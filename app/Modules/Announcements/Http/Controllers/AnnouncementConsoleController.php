<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcements\Actions\SaveAnnouncement;
use App\Modules\Announcements\Http\Requests\SaveAnnouncementRequest;
use App\Modules\Announcements\Models\Announcement;
use App\Modules\Announcements\Support\AnnouncementPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Avisos»: what Humetría tells the companies in their panel, for the superadmin alone. */
final class AnnouncementConsoleController extends Controller
{
    public function index(Request $request, AnnouncementPage $page): Response
    {
        Gate::authorize('manage-announcements');

        return Inertia::render('announcements/index', $page->props($request->string('view')->toString()));
    }

    public function store(SaveAnnouncementRequest $request, SaveAnnouncement $save): RedirectResponse
    {
        $save->handle($request->announcement());

        return back()->with('status', 'Aviso guardado.');
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, SaveAnnouncement $save): RedirectResponse
    {
        $save->handle($request->announcement(), $announcement);

        return back()->with('status', 'Aviso guardado.');
    }
}
