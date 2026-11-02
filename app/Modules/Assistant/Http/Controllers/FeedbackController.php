<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Actions\RateMessage;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Http\Requests\FeedbackRequest;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use Illuminate\Http\Response;

final readonly class FeedbackController
{
    public function __construct(private AssistantScopeFactory $scopes, private RateMessage $rate) {}

    public function store(FeedbackRequest $request): Response
    {
        try {
            $this->rate->handle($this->scopes->make($request->user() ?? abort(401)), $request->messageId(), $request->rating(), $request->reason());
        } catch (ConversationNotFound) {
            abort(404);
        }

        return response()->noContent();
    }
}
