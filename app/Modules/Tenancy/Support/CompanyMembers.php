<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Models\Tenant;
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
    }

    /** @return list<MemberSummary> */
    private function load(): array
    {
        $roles = $this->roles->inCompany($this->tenant());

        return array_values(User::query()
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

    /** @return list<MemberSummary> */
    private function loadReviewers(): array
    {
        $names = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'review-workplaces')
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

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->current->id());
    }
}
