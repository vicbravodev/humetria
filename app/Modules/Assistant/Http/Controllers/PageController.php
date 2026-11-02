<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\Conversations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Humi on a page of its own: the history of the person, a page at a time, beside the conversation. */
final readonly class PageController
{
    private const int PER_PAGE = 15;

    public function __construct(private AssistantScopeFactory $scopes, private Conversations $conversations) {}

    public function index(Request $request): Response
    {
        $scope = $this->scopes->make($request->user() ?? abort(401));

        return Inertia::render('assistant/index', [
            'conversations' => $this->conversations->page($scope, self::PER_PAGE)->withQueryString(),
        ]);
    }
}
