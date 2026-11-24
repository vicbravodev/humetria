<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\ReviewOfflineMark;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Http\Requests\ReviewOfflineMarkRequest;
use App\Modules\Attendance\Http\Requests\VerifyOfflineMarksRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * RH reviews the marks made without a connection (criterio 5.4), from the tab of Correcciones: verifies one or the selected
 * ones, or says one is incorrect with its correction. Owner and hr; a mark of another company is a 404.
 */
final readonly class OfflineMarkController
{
    private const array REFUSED = [
        'mark_changed' => 'La marca ya se corrigió o anuló desde que la abriste.',
        'sequence_invalid' => 'Con esa hora el día queda en desorden. Escribe otra hora o deja la marca anulada.',
        'future_time' => 'Esa hora aún no llega. Revisa la hora y el día.',
        'not_offline' => 'Esa marca no se hizo sin conexión.',
    ];

    public function verify(ReviewOfflineMarkRequest $request, mixed $tenant, string $mark, ReviewOfflineMark $review): RedirectResponse
    {
        return $this->review($request, $mark, $review, ReviewDecision::Verified, 'Marca verificada.');
    }

    public function reject(ReviewOfflineMarkRequest $request, mixed $tenant, string $mark, ReviewOfflineMark $review): RedirectResponse
    {
        return $this->review($request, $mark, $review, ReviewDecision::Incorrect, 'Marca corregida. La persona verá tu nota.');
    }

    public function verifyMany(VerifyOfflineMarksRequest $request, mixed $tenant, ReviewOfflineMark $review): RedirectResponse
    {
        $done = $review->verifyMany($request->ids(), ($request->user() ?? abort(401))->id);
        $message = trans_choice('{1} Verificaste :count marca.|[0,*] Verificaste :count marcas.', $done['verified']);

        if ($done['skipped'] > 0) {
            $message .= ' '.trans_choice('{1} :count cambió desde que abriste la lista: revísala una por una.|[2,*] :count cambiaron desde que abriste la lista: revísalas una por una.', $done['skipped']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function review(ReviewOfflineMarkRequest $request, string $mark, ReviewOfflineMark $review, ReviewDecision $decision, string $done): RedirectResponse
    {
        try {
            $review->handle($mark, $decision, ($request->user() ?? abort(401))->id, $request->note(), $request->correctedAt());
            Inertia::flash('toast', ['type' => 'success', 'message' => $done]);
        } catch (CorrectionRejected $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => self::REFUSED[$refused->reason] ?? 'No se pudo guardar la revisión.']);
        }

        return back();
    }
}
