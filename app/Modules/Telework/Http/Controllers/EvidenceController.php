<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\RequestEvidence;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The Comisión asks for more evidence about a workplace: photos (the person sends them from their portal) or a remote
 * check. Only the assigned members of the person's work center.
 */
final class EvidenceController extends Controller
{
    public function __construct(private readonly ReviewAccess $access) {}

    public function store(Request $request, mixed $tenant, string $workplace, RequestEvidence $evidence): RedirectResponse
    {
        $found = Str::isUuid($workplace) ? Workplace::query()->find($workplace) : null;
        abort_if($found === null, 404);
        $user = $request->user();
        abort_if($user === null, 403);
        $this->access->member($user->id, $this->access->workCenterOf($found));

        $request->validate(['kind' => ['required', Rule::enum(EvidenceKind::class)], 'note' => ['nullable', 'string', 'max:1000']]);
        $evidence->handle($found, EvidenceKind::from($request->string('kind')->toString()), $request->string('note')->toString(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitud registrada.']);

        return back();
    }
}
