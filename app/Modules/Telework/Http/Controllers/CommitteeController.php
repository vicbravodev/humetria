<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Telework\Actions\AssignCommitteeMember;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Actions\UnassignCommitteeMember;
use App\Modules\Telework\Http\Requests\CommitteeMemberRequest;
use App\Modules\Telework\Http\Requests\CommitteeRequest;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Comisión de seguridad e higiene of one work center: who sits on it and when it was constituted. A center outside
 * the company, archived or unknown is a 404, as is a committee user's other centers.
 */
final class CommitteeController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, Members $members, CommitteeGate $gate): Response
    {
        Gate::authorize('view-telework');
        $center = $this->center($workCenters, $workCenter);
        $user = $request->user();
        $canManage = Gate::allows('manage-telework');
        abort_unless($user !== null && ($canManage || $gate->isMember($user->id, $center->id)), 404);

        $committee = SafetyCommittee::query()->where('work_center_id', $center->id)->first();
        // A fresh get() is already a list; a spread renumbers what it spreads.
        $assigned = array_values(CommitteeAssignment::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->where('work_center_id', $center->id)->get(['user_id'])->map(fn (CommitteeAssignment $assignment): int => $assignment->user_id)->all());
        $reviewers = $members->reviewers();
        $row = fn (MemberSummary $member): array => ['id' => $member->userId, 'name' => $member->name, 'email' => $member->email, 'role' => $member->role->label(), 'active' => true];

        return Inertia::render('telework/committee', [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'committee' => $committee === null ? null : [
                'constitutedOn' => $committee->constituted_on->toDateString(),
                'hasAct' => $committee->act_path !== null,
                'members' => [
                    ...array_map($row, array_values(array_filter($reviewers, fn (MemberSummary $member): bool => in_array($member->userId, $assigned, true)))), // @pest-mutate-ignore: UnwrapArrayValues
                    ...$this->withoutAccess($assigned, $reviewers),
                ],
            ],
            // The company's people are for whoever manages the committee; the committee role does not see its users.
            'candidates' => $canManage ? array_map($row, array_values(array_filter($reviewers, fn (MemberSummary $member): bool => ! in_array($member->userId, $assigned, true)))) : Inertia::optional(fn (): array => []),
            'canManage' => $canManage,
            // Whoever sits on this Comisión edits the checklist; RH reads it.
            'canEditChecklist' => $gate->isMember($user->id, $center->id),
            // The inbox of submitted checklists is for the same assigned members.
            'canReview' => $committee !== null && $gate->isMember($user->id, $center->id),
        ]);
    }

    /**
     * Assigned users who no longer hold a reviewing role (demoted, or no longer in the company): kept in the list, marked,
     * so the manager can take their seat away.
     *
     * @param  list<int>  $assigned
     * @param  list<MemberSummary>  $reviewers
     * @return list<array{id: int, name: string, email: string, role: string, active: bool}>
     */
    private function withoutAccess(array $assigned, array $reviewers): array
    {
        $active = array_map(fn (MemberSummary $member): int => $member->userId, $reviewers);

        return array_values(User::query()->whereIn('id', array_diff($assigned, $active)) // @pest-mutate-ignore: UnwrapArrayValues
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Sin acceso: ya no tiene un rol de la Comisión', 'active' => false])
            ->all());
    }

    public function act(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, CommitteeGate $gate, FileStore $files): StreamedResponse
    {
        Gate::authorize('view-telework');
        $center = $this->center($workCenters, $workCenter);
        $user = $request->user();
        abort_unless($user !== null && (Gate::allows('manage-telework') || $gate->isMember($user->id, $center->id)), 404);

        $path = SafetyCommittee::query()->where('work_center_id', $center->id)->first()?->act_path;
        abort_if($path === null, 404);

        $response = $files->stream($path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function store(CommitteeRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, RegisterCommittee $register): RedirectResponse
    {
        $center = $this->center($workCenters, $workCenter);
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore

        $register->handle($center->id, $request->constitutedOn(), $request->actFile(), $request->memberIds(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comisión registrada. Ya existe el borrador de la lista de verificación.']);

        return redirect()->route('tenant.telework.committee.show', [$tenant, $center->id]);
    }

    public function assign(CommitteeMemberRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, AssignCommitteeMember $assign): RedirectResponse
    {
        $center = $this->center($workCenters, $workCenter);
        abort_unless(SafetyCommittee::query()->where('work_center_id', $center->id)->exists(), 404);

        $assign->handle($center->id, $request->memberId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integrante agregado a la Comisión.']);

        return redirect()->route('tenant.telework.committee.show', [$tenant, $center->id]);
    }

    public function unassign(Request $request, mixed $tenant, string $workCenter, int $user, WorkCenters $workCenters, UnassignCommitteeMember $unassign): RedirectResponse
    {
        Gate::authorize('manage-telework');
        $center = $this->center($workCenters, $workCenter);

        abort_unless($unassign->handle($center->id, $user), 404);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integrante quitado de la Comisión.']);

        return redirect()->route('tenant.telework.committee.show', [$tenant, $center->id]);
    }

    private function center(WorkCenters $workCenters, string $id): WorkCenterSummary
    {
        $center = Str::isUuid($id) ? $workCenters->findActive($id) : null;
        abort_if($center === null, 404);

        return $center;
    }
}
