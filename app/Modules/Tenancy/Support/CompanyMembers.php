<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class CompanyMembers implements Members
{
    public function __construct(private CurrentTenant $current, private CompanyRoles $roles) {}

    public function all(): array
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
