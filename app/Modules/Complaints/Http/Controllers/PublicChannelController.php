<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Actions\OpenChannel;
use App\Modules\Complaints\Exceptions\ChannelUnavailable;
use App\Modules\Complaints\Support\PublicPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page where a person presents a complaint: no account, no session, no cookie. The code in the address is the credential
 * of the company; nothing is read from the request but that (not the address, the browser or the referrer).
 */
final class PublicChannelController extends Controller
{
    public function show(Request $request, string $code, OpenChannel $open, PublicPages $pages): Response
    {
        try {
            $opened = $open->handle($code);
        } catch (ChannelUnavailable) {
            return Inertia::render('complaints-public/unavailable', $pages->unavailable())->toResponse($request)->setStatusCode(404);
        }

        if (! $opened->channel->isOpen()) {
            return Inertia::render('complaints-public/unavailable', $pages->paused($opened))->toResponse($request);
        }

        return Inertia::render('complaints-public/form', $pages->form($opened))->toResponse($request);
    }
}
