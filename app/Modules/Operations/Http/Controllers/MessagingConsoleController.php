<?php

declare(strict_types=1);

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Support\MessagingOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** «Mensajería», for the superadmin: WhatsApp and mail of the whole platform, read only. */
final class MessagingConsoleController extends Controller
{
    /** The tabs of the page; any other view opens the summary. */
    private const array VIEWS = ['summary', 'templates', 'mail', 'companies'];

    public function __invoke(Request $request, MessagingOverview $overview): Response
    {
        Gate::authorize('view-operations');
        $view = $request->string('view')->toString();

        return Inertia::render('operations/messaging', [
            ...$overview->handle(),
            'view' => in_array($view, self::VIEWS, true) ? $view : 'summary',
        ]);
    }
}
