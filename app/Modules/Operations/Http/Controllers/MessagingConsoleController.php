<?php

declare(strict_types=1);

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\MessagingOverview;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Mensajería», for the superadmin: WhatsApp and mail of the whole platform, read only. */
final class MessagingConsoleController extends Controller
{
    public function __invoke(MessagingOverview $overview): Response
    {
        Gate::authorize('view-operations');

        return Inertia::render('operations/messaging', $overview->handle());
    }
}
