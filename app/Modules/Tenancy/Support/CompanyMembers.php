<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Models\Tenant;

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
