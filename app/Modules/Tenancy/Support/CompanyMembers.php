<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;

/**
 * Bound as scoped, so one request asks for the people of its company once, however many modules read them. The memo is
 * kept per company, and CompanyRoles drops it when a role changes.
 */
final class CompanyMembers implements Members
{
    /** @var array<string, list<MemberSummary>> */
    private array $memo = [];

    /** @var array<string, list<MemberSummary>> */
    private array $reviewing = [];

    /** @var array<string, list<MemberSummary>> */
    private array $handling = [];

    /** @var array<string, list<MemberSummary>> */
    private array $managing = [];

    public function __construct(private readonly CurrentTenant $current, private readonly CompanyRoles $roles) {}

    public function all(): array
    {
        $tenantId = $this->current->id();

        return $this->memo[$tenantId] ??= $this->load();
    }

    /** Drops the memo: a role changed. */
    public function forget(): void
    {
        $this->memo = [];
        $this->reviewing = [];
        $this->handling = [];
        $this->managing = [];
    }

    /** @return list<MemberSummary> */
    private function load(): array
    {
        $roles = $this->roles->inCompany($this->tenant());

        // A fresh get() is already a list.
        return array_values(User::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->whereIn('id', array_keys($roles))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): MemberSummary => new MemberSummary($user->id, $user->name, $user->email, $roles[$user->id]))
            ->all());
    }

    public function reviewers(): array
    {
        return $this->reviewing[$this->current->id()] ??= $this->loadReviewers();
    }

    public function complaintHandlers(): array
    {
        return $this->handling[$this->current->id()] ??= $this->withPermission('handle-complaints');
    }

    public function complaintManagers(): array
    {
        return $this->managing[$this->current->id()] ??= $this->withPermission('manage-complaint-channel');
    }

    public function screeningViewers(): array
    {
        return $this->withPermission('view-trauma-screenings');
    }

    /** @return list<MemberSummary> */
    private function loadReviewers(): array
    {
        return $this->withPermission('review-workplaces');
    }

    /** @return list<MemberSummary> The members whose role has the permission. */
    private function withPermission(string $permission): array
    {
        $names = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', $permission)
            ->pluck('roles.name')
            ->all();

        return array_values(array_filter($this->all(), fn (MemberSummary $member): bool => in_array($member->role->value, $names, true)));
    }

    public function find(int $userId): ?MemberSummary
    {
        foreach ($this->all() as $member) {
            if ($member->userId === $userId) {
                return $member;
            }
        }

        return null;
    }

    public function pendingInvitations(): int
    {
        // TenantInvitation has no tenant scope (it is read across companies): the company is filtered here.
        return TenantInvitation::query()
            ->where('tenant_id', $this->current->id())
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->count();
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->current->id());
    }
}
