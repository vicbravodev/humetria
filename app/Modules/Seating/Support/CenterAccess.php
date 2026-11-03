<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\Request;

/**
 * The work center a request asks for, if the signed-in user may see it: a center of another company, an archived one, a
 * made-up one and (for the Comisión) one it does not sit on are all a 404.
 */
final readonly class CenterAccess
{
    public function __construct(private WorkCenters $workCenters, private VisibleCenters $visible) {}

    public function userId(Request $request): int
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user->id;
    }

    public function find(Request $request, string $workCenterId): WorkCenterSummary
    {
        $center = $this->workCenters->findActive($workCenterId);
        abort_if($center === null, 404);
        $limit = $this->visible->limitedTo($this->userId($request));
        abort_unless($limit === null || in_array($center->id, $limit, true), 404);

        return $center;
    }
}
