<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Complaints\Actions\AcknowledgeComplaint;
use App\Modules\Complaints\Actions\AddInternalNote;
use App\Modules\Complaints\Actions\CloseCase;
use App\Modules\Complaints\Actions\RecordCaseExport;
use App\Modules\Complaints\Actions\RecuseFromCase;
use App\Modules\Complaints\Actions\ReopenCase;
use App\Modules\Complaints\Actions\SetLegalHold;
use App\Modules\Complaints\Actions\ShareCase;
use App\Modules\Complaints\Actions\StartReview;
use App\Modules\Complaints\Actions\WriteToReporter;
use App\Modules\Complaints\Http\Requests\AcknowledgeRequest;
use App\Modules\Complaints\Http\Requests\CaseMessageRequest;
use App\Modules\Complaints\Http\Requests\CloseCaseRequest;
use App\Modules\Complaints\Http\Requests\ShareCaseRequest;
use App\Modules\Complaints\Support\HeldCase;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * What a handler does with a case they have: acknowledge it, move it to review, write to the person, take notes, share it,
 * step aside, hold it, close it, open it again and print it. Every route finds the case among the assigned ones (a 404
 * otherwise); every change is audited and logged without a word of what the case says.
 */
final class CaseActionController extends Controller
{
    public function __construct(private readonly HeldCase $held) {}

    public function acknowledge(AcknowledgeRequest $request, mixed $tenant, string $complaint, AcknowledgeComplaint $acknowledge, #[CurrentUser] User $user): RedirectResponse
    {
        $acknowledge->handle($request->case(), $user->id, $request->text());

        return $this->done($tenant, $complaint, 'Acuse enviado.');
    }

    public function review(Request $request, mixed $tenant, string $complaint, StartReview $review, #[CurrentUser] User $user): RedirectResponse
    {
        $review->handle($this->held->of($request, $complaint), $user->id);

        return $this->done($tenant, $complaint, 'El caso pasó a revisión.');
    }

    public function message(CaseMessageRequest $request, mixed $tenant, string $complaint, WriteToReporter $write, #[CurrentUser] User $user): RedirectResponse
    {
        $write->handle($request->case(), $request->body(), $user->id);

        return $this->done($tenant, $complaint, 'Mensaje enviado a la persona.');
    }

    public function note(CaseMessageRequest $request, mixed $tenant, string $complaint, AddInternalNote $note, #[CurrentUser] User $user): RedirectResponse
    {
        $note->handle($request->case(), $request->body(), $user->id);

        return $this->done($tenant, $complaint, 'Nota guardada.');
    }

    public function share(ShareCaseRequest $request, mixed $tenant, string $complaint, ShareCase $share, #[CurrentUser] User $user): RedirectResponse
    {
        $share->handle($request->case(), $request->userId(), $user->id);

        return $this->done($tenant, $complaint, 'Caso compartido.');
    }

    public function recuse(Request $request, mixed $tenant, string $complaint, RecuseFromCase $recuse, #[CurrentUser] User $user): RedirectResponse
    {
        $recuse->handle($this->held->of($request, $complaint), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Te excusaste del caso.']);

        return to_route('tenant.complaints.index', $tenant);
    }

    public function hold(Request $request, mixed $tenant, string $complaint, SetLegalHold $hold): RedirectResponse
    {
        $case = $this->held->of($request, $complaint);
        $hold->handle($case, $request->boolean('on_hold'));

        return $this->done($tenant, $complaint, $request->boolean('on_hold') ? 'Retención legal activada.' : 'Retención legal levantada.');
    }

    public function close(CloseCaseRequest $request, mixed $tenant, string $complaint, CloseCase $close, #[CurrentUser] User $user): RedirectResponse
    {
        $close->handle($request->case(), $request->resolution(), $user->id, $request->answer());

        return $this->done($tenant, $complaint, 'Caso cerrado.');
    }

    public function reopen(Request $request, mixed $tenant, string $complaint, ReopenCase $reopen, #[CurrentUser] User $user): RedirectResponse
    {
        $reopen->handle($this->held->of($request, $complaint), $user->id);

        return $this->done($tenant, $complaint, 'Caso abierto de nuevo.');
    }

    public function export(Request $request, mixed $tenant, string $complaint, RecordCaseExport $export): RedirectResponse
    {
        $export->handle($this->held->of($request, $complaint));

        return to_route('tenant.complaints.cases.show', [$tenant, $complaint]);
    }

    private function done(mixed $tenant, string $complaint, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tenant.complaints.cases.show', [$tenant, $complaint]);
    }
}
