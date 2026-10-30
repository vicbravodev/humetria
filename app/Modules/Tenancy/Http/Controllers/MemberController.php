<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Actions\CancelInvitation;
use App\Modules\Tenancy\Actions\ChangeMemberRole;
use App\Modules\Tenancy\Actions\InviteMember;
use App\Modules\Tenancy\Actions\RemoveMember;
use App\Modules\Tenancy\Actions\ResendInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Http\Requests\ChangeMemberRoleRequest;
use App\Modules\Tenancy\Http\Requests\InviteMemberRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\DisplayDate;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * «Usuarios»: who has access to the company and with which role. Only its owner manages it; the owner's own
 * membership is never changed here (that is the superadmin's console), so nobody removes themself.
 */
final class MemberController extends Controller
{
    private const int PER_PAGE = 25;

    public function __construct(private readonly CompanyRoles $roles) {}

    public function index(Tenant $tenant, #[CurrentUser] User $user): Response
    {
        $this->authorizeOwner();
        $roles = $this->roles->inCompany($tenant);

        $owner = array_search(TenantRole::Owner, $roles, true);

        return Inertia::render('tenancy/members', [
            'members' => $tenant->memberships()
                ->with('user')
                ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
                ->whereIn('tenant_memberships.user_id', array_keys($roles))
                // The owner first, then by name.
                ->orderByRaw('tenant_memberships.user_id = ? desc', [$owner === false ? 0 : $owner])
                ->orderBy('users.name')
                ->orderBy('tenant_memberships.id')
                ->select('tenant_memberships.*')
                ->paginate(self::PER_PAGE, pageName: 'members')
                ->withQueryString()
                ->through(fn (TenantMembership $member): array => $this->memberRow($member, $roles[$member->user_id] ?? null, $user)),
            'invitations' => $tenant->invitations()->open()->where('role', '<>', TenantRole::Owner)->latest()->orderByDesc('id')
                ->paginate(self::PER_PAGE, pageName: 'invitations')
                ->withQueryString()
                ->through(fn (TenantInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'name' => $invitation->name,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'status' => $invitation->status()->value,
                    'status_label' => $invitation->status()->label(),
                    'expires_on' => DisplayDate::day($invitation->expires_at),
                ]),
            'roles' => array_map(
                fn (TenantRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                [TenantRole::Hr, TenantRole::Viewer],
            ),
        ]);
    }

    public function invite(InviteMemberRequest $request, Tenant $tenant, InviteMember $inviteMember, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeOwner();
        $inviteMember->handle($tenant, $request->toData(), $user);

        return $this->done($tenant, 'Invitación enviada.');
    }

    public function resend(Tenant $tenant, string $invitation, ResendInvitation $resendInvitation, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeOwner();

        try {
            $resendInvitation->handle($this->invitation($tenant, $invitation), $user);
        } catch (InvitationNoLongerValid) {
            return $this->noLongerOpen($tenant);
        }

        return $this->done($tenant, 'Invitación reenviada.');
    }

    public function cancel(Tenant $tenant, string $invitation, CancelInvitation $cancelInvitation, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeOwner();

        try {
            $cancelInvitation->handle($this->invitation($tenant, $invitation), $user);
        } catch (InvitationNoLongerValid) {
            return $this->noLongerOpen($tenant);
        }

        return $this->done($tenant, 'Invitación cancelada.');
    }

    public function update(ChangeMemberRoleRequest $request, Tenant $tenant, string $member, ChangeMemberRole $changeMemberRole, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeOwner();
        $changeMemberRole->handle($this->member($tenant, $member), $request->role(), $user);

        return $this->done($tenant, 'Rol actualizado.');
    }

    public function destroy(Tenant $tenant, string $member, RemoveMember $removeMember, #[CurrentUser] User $user): RedirectResponse
    {
        $this->authorizeOwner();
        $removeMember->handle($this->member($tenant, $member), $user);

        return $this->done($tenant, 'Acceso quitado.');
    }

    /**
     * Only people with a role in the company are listed, so both are there; anything else is broken data.
     *
     * @return array{id: int, name: string, email: string, role: string, role_label: string, is_you: bool, since: string}
     */
    private function memberRow(TenantMembership $member, ?TenantRole $role, User $you): array
    {
        $user = $member->user ?? throw new LogicException("Membership [{$member->id}] has no user.");
        $role ??= throw new LogicException("Membership [{$member->id}] has no role.");

        return [
            'id' => $member->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role->value,
            'role_label' => $role->label(),
            'is_you' => $user->id === $you->id,
            'since' => DisplayDate::day($member->created_at),
        ];
    }

    private function authorizeOwner(): void
    {
        Gate::authorize('manage-members');
    }

    /** Another company's person, or the owner, is not found here: the same 404 as one that does not exist. */
    private function member(Tenant $tenant, string $id): TenantMembership
    {
        $member = $tenant->memberships()->findOrFail($id);

        abort_if(($this->roles->inCompany($tenant)[$member->user_id] ?? null) === TenantRole::Owner, 404);

        return $member;
    }

    private function invitation(Tenant $tenant, string $id): TenantInvitation
    {
        return $tenant->invitations()->where('role', '<>', TenantRole::Owner)->findOrFail($id);
    }

    private function done(Tenant $tenant, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('tenant.members.index', $tenant);
    }

    private function noLongerOpen(Tenant $tenant): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta invitación ya no está abierta.']);

        return redirect()->route('tenant.members.index', $tenant);
    }
}
