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

    /** @var array<string, list<MemberSummary>> by company and permission */
    private array $holders = [];

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
        $this->holders = [];
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
        return $this->holding('review-workplaces');
    }

    public function complaintHandlers(): array
    {
        return $this->holding('handle-complaints');
    }

    public function complaintManagers(): array
    {
        return $this->holding('manage-complaint-channel');
    }

    public function screeningViewers(): array
    {
        return $this->holding('view-trauma-screenings');
    }

    public function holding(string $permission): array
    {
        $tenantId = $this->current->id();

        return $this->holders[$tenantId.'|'.$permission] ??= $this->loadHolding($tenantId, $permission);
    }

    /** @return list<MemberSummary> */
    private function loadHolding(string $tenantId, string $permission): array
    {
        // A tool the company has not contracted counts for nothing, as in a person's effective permissions.
        if (($definition = PermissionCatalog::find($permission)) !== null && ! PermissionCatalog::available($tenantId, $definition)) {
            return [];
        }

        $type = (new User)->getMorphClass();
        $direct = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.tenant_id', $tenantId)->where('model_has_permissions.model_type', $type)->where('permissions.name', $permission)
            ->select('model_has_permissions.model_id');
        $ids = DB::table('role_has_permissions')
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.tenant_id', $tenantId)->where('model_has_roles.model_type', $type)->where('permissions.name', $permission)
            ->select('model_has_roles.model_id')
            ->union($direct)
            ->pluck('model_id')
            ->all();

        return array_values(array_filter($this->all(), fn (MemberSummary $member): bool => in_array($member->userId, $ids, true)));
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
