<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Actions\OpenChannel;
use App\Modules\Complaints\Actions\OpenFollowUp;
use App\Modules\Complaints\Actions\SendReporterMessage;
use App\Modules\Complaints\Actions\WithdrawComplaint;
use App\Modules\Complaints\Exceptions\ChannelUnavailable;
use App\Modules\Complaints\Http\Requests\FollowUpMessageRequest;
use App\Modules\Complaints\Http\Requests\FollowUpRequest;
use App\Modules\Complaints\Support\FollowUpPages;
use App\Modules\Complaints\Support\PublicPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The follow-up of a complaint by the person who made it, with the reference and the key in the body of every request: no
 * account, no session, no cookie. The browser keeps them in memory only; closing the page forgets them.
 */
final class PublicFollowUpController extends Controller
{
    public function show(Request $request, string $code, OpenChannel $open, PublicPages $pages): Response
    {
        try {
            $opened = $open->handle($code);
        } catch (ChannelUnavailable) {
            return Inertia::render('complaints-public/unavailable', $pages->unavailable())->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('complaints-public/follow-up', $pages->followUp($opened))->toResponse($request);
    }

    public function open(FollowUpRequest $request, OpenFollowUp $open, FollowUpPages $pages): JsonResponse
    {
        return new JsonResponse($pages->view($open->handle($request->reference(), $request->secret())));
    }

    public function message(FollowUpMessageRequest $request, OpenFollowUp $open, SendReporterMessage $send, FollowUpPages $pages): JsonResponse
    {
        $complaint = $open->handle($request->reference(), $request->secret());
        $send->handle($complaint, $request->body());

        return new JsonResponse($pages->view($complaint->refresh()), 201);
    }

    public function withdraw(FollowUpRequest $request, OpenFollowUp $open, WithdrawComplaint $withdraw, FollowUpPages $pages): JsonResponse
    {
        $complaint = $open->handle($request->reference(), $request->secret());
        $withdraw->handle($complaint);

        return new JsonResponse($pages->view($complaint->refresh()));
    }
}
