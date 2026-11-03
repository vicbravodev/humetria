<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\Complaint;
use Illuminate\Http\Request;

/**
 * The case an action works on, found the one way a case is found: among those assigned to the person. A person who may
 * handle complaints but has no assignment gets a 404, as for a case that does not exist; one who may not gets a 403.
 */
final readonly class HeldCase
{
    public function __construct(private CaseAccess $access) {}

    public function of(Request $request, string $complaintId): Complaint
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('handle-complaints'), 403);

        $case = $this->access->find($user->id, $complaintId);
        abort_if($case === null, 404);

        return $case;
    }
}
