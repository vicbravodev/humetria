<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\CorrectMarkDirectly;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Http\Requests\CorrectMarkRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Owner and hr correct a mark with no request first, from the report. A mark of another company is a 404. */
final readonly class MarkCorrectionController
{
    private const array REFUSED = [
        'sequence_invalid' => 'Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta). Revisa la hora.',
        'future_time' => 'Esa hora aún no llega. Revisa la hora y el día.',
        'mark_changed' => 'Esa marca ya se corrigió. Recarga la página y corrige la vigente.',
    ];

    public function __invoke(CorrectMarkRequest $request, mixed $tenant, string $mark, CorrectMarkDirectly $correct): RedirectResponse
    {
        try {
            $correct->handle($mark, $request->at(), $request->note(), ($request->user() ?? abort(401))->id);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Marca corregida. Queda en la bitácora a tu nombre, con tu nota.']);
        } catch (CorrectionRejected $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => self::REFUSED[$refused->reason] ?? 'No se pudo corregir la marca.']);
        }

        return back();
    }
}
