<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\LiveFacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** What is true now in the company, in short lines, for the first questions of Humi and the invitations of its bubble. */
final readonly class LiveFactsController
{
    public function __construct(private AssistantScopeFactory $scopes, private LiveFacts $facts) {}

    public function show(Request $request): JsonResponse
    {
        $scope = $this->scopes->make($request->user() ?? abort(401));

        // An object even when empty: the browser reads facts by key.
        return response()->json(['facts' => $this->facts->for($scope)], options: JSON_FORCE_OBJECT);
    }
}
