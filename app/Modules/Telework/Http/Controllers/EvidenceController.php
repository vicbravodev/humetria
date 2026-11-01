<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\RequestEvidence;
use App\Modules\Telework\Http\Requests\EvidenceRequestRequest;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * The Comisión asks for more evidence about a workplace: photos (the person sends them from their portal) or a remote
 * check. Only the assigned members of the person's work center.
 */
final class EvidenceController extends Controller
{
    public function __construct(private readonly ReviewAccess $access) {}

    public function store(EvidenceRequestRequest $request, mixed $tenant, string $workplace, RequestEvidence $evidence): RedirectResponse
    {
        $found = Str::isUuid($workplace) ? Workplace::query()->find($workplace) : null;
        abort_if($found === null, 404);
        $user = $request->user();
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall
        $this->access->member($user->id, $this->access->workCenterOf($found));

        $evidence->handle($found, $request->kind(), $request->note(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud registrada.']);

        return back();
    }
}
