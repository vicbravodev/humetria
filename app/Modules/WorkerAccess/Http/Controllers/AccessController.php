<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\WorkerAccess\Actions\InviteCenter;
use App\Modules\WorkerAccess\Actions\IssueAccessLink;
use App\Modules\WorkerAccess\Actions\ReissueAccess;
use App\Modules\WorkerAccess\Actions\RevokeCredential;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Http\Requests\AccessPersonRequest;
use App\Modules\WorkerAccess\Http\Requests\InviteCenterRequest;
use App\Modules\WorkerAccess\Support\AccessPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Accesos»: the people's keys and their links. Owner, hr and viewer read it; owner and hr invite a person or a whole center,
 * reissue a person's access and revoke a key. The link goes only to the person's email; RH sees it once, to hand it over,
 * only for a person without one (a flash, never kept nor logged). A person, a center or a key not the company's is a 404.
 */
final readonly class AccessController
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster) {}

    public function index(Request $request, AccessPages $pages): Response
    {
        Gate::authorize('view-worker-access');

        return Inertia::render('worker-access/access', $pages->index(
            $this->center($request->string('center')->toString()),
            $request->string('status')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
        ));
    }

    public function invite(AccessPersonRequest $request, IssueAccessLink $issue): RedirectResponse
    {
        $outcome = $issue->handle($request->employeeId(), ($request->user() ?? abort(401))->id);

        return $this->delivered($outcome, $request->employeeId(), 'Enviamos la invitación al correo de :name.', null);
    }

    /** CUJ 7: every key and session of the person stops, and a new link goes to them (or to RH, once, without email). */
    public function reissue(AccessPersonRequest $request, ReissueAccess $reissue): RedirectResponse
    {
        $outcome = $reissue->handle($request->employeeId(), ($request->user() ?? abort(401))->id);

        return $this->delivered(
            $outcome,
            $request->employeeId(),
            'Reemitimos el acceso de :name: sus llaves y sesiones dejaron de servir y le enviamos un enlace nuevo a su correo.',
            'Reemitimos el acceso de :name: sus llaves y sesiones dejaron de servir.',
        );
    }

    /** Their invitation to every person of a center without a key; who has no email is counted, for RH to hand theirs over. */
    public function inviteCenter(InviteCenterRequest $request, InviteCenter $invite): RedirectResponse
    {
        $counts = $invite->handle($request->workCenterId(), ($request->user() ?? abort(401))->id) ?? abort(404);

        Inertia::flash('toast', ['type' => 'success', 'message' => self::batchMessage($counts['sent'], $counts['with_link'], $counts['without_email'])]);

        return back();
    }

    /** Stops one key at once: it no longer enters nor marks. A lost phone is a reissue, which also ends the sessions. */
    public function revoke(mixed $tenant, string $credential, RevokeCredential $revoke): RedirectResponse
    {
        Gate::authorize('manage-worker-access');
        $revoke->handle($credential);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Llave revocada. Ya no sirve para entrar ni para marcar.']);

        return back();
    }

    /** The center a list is narrowed to: none when not asked; a 404 for one not in use of the company, or not an id. */
    private function center(string $id): ?WorkCenterSummary
    {
        if ($id === '') {
            return null;
        }

        return (Str::isUuid($id) ? $this->workCenters->findActive($id) : null) ?? abort(404);
    }

    /** Tells RH how it went; for a person without email, the link once (shown this once: never kept, never logged). */
    private function delivered(InvitationOutcome $outcome, string $employeeId, string $sent, ?string $handOver): RedirectResponse
    {
        abort_if($outcome->status === InvitationOutcome::UNAVAILABLE, 404);
        $name = $this->roster->find($employeeId)->name ?? '';

        if ($outcome->url === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => str_replace(':name', $name, $sent)]);
        } else {
            Inertia::flash('accessLink', ['name' => $name, 'url' => $outcome->url]);

            if ($handOver !== null) {
                Inertia::flash('toast', ['type' => 'success', 'message' => str_replace(':name', $name, $handOver)]);
            }
        }

        return back();
    }

    private static function batchMessage(int $sent, int $withLink, int $withoutEmail): string
    {
        if ($sent === 0 && $withLink === 0 && $withoutEmail === 0) {
            return 'No había a quién invitar: todas las personas del centro tienen llave o no tienen correo.';
        }

        return implode(' ', array_filter([
            match ($sent) {
                0 => 'No enviamos ninguna invitación.',
                1 => 'Enviamos 1 invitación.',
                default => "Enviamos {$sent} invitaciones.",
            },
            match ($withLink) {
                0 => null,
                1 => 'Omitimos 1 persona que ya tenía un enlace vigente.',
                default => "Omitimos {$withLink} personas que ya tenían un enlace vigente.",
            },
            match ($withoutEmail) {
                0 => null,
                1 => '1 persona no tiene correo: crea su enlace desde su renglón y entrégaselo.',
                default => "{$withoutEmail} personas no tienen correo: crea su enlace desde su renglón y entrégaselo.",
            },
        ]));
    }
}
