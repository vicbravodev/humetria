<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\AssignCommitteeMember;
use App\Modules\Telework\Actions\RegisterCommittee;
use App\Modules\Telework\Actions\UnassignCommitteeMember;
use App\Modules\Telework\Http\Requests\CommitteeMemberRequest;
use App\Modules\Telework\Http\Requests\CommitteeRequest;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Support\CommitteeGate;
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
        $assigned = CommitteeAssignment::query()->where('work_center_id', $center->id)->pluck('user_id')->all();
        $row = fn (MemberSummary $member): array => ['id' => $member->userId, 'name' => $member->name, 'email' => $member->email, 'role' => $member->role->label()];

        return Inertia::render('telework/committee', [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'committee' => $committee === null ? null : [
                'constitutedOn' => $committee->constituted_on->toDateString(),
                'hasAct' => $committee->act_path !== null,
                'members' => array_values(array_map($row, array_filter($members->reviewers(), fn (MemberSummary $member): bool => in_array($member->userId, $assigned, true)))),
            ],
            'candidates' => array_values(array_map($row, array_filter($members->reviewers(), fn (MemberSummary $member): bool => ! in_array($member->userId, $assigned, true)))),
            'canManage' => $canManage,
        ]);
    }

    public function store(CommitteeRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, RegisterCommittee $register): RedirectResponse
    {
        $center = $this->center($workCenters, $workCenter);
        abort_if($request->user() === null, 403);

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
