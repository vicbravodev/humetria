<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Contracts\MemberLookup;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class CompanyMemberLookup implements MemberLookup
{
    public function __construct(private CompanyRoles $roles) {}

    public function userIdByEmail(string $tenantId, string $email): ?int
    {
        $id = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('user', fn (Builder $user) => $user->whereRaw('lower(email) = ?', [Str::lower($email)]))
            ->value('user_id');

        return is_int($id) ? $id : null;
    }

    public function permissionsOf(string $tenantId, int $userId): array
    {
        return array_keys($this->roles->effectivePermissions(User::query()->findOrFail($userId), Tenant::query()->findOrFail($tenantId)));
    }

    public function permissionsOfMany(string $tenantId, array $userIds): array
    {
        return $userIds === [] ? [] : $this->roles->effectivePermissionNames(Tenant::query()->findOrFail($tenantId), $userIds);
    }

    public function isOwner(string $tenantId, int $userId): bool
    {
        return $this->roles->roleOf(User::query()->findOrFail($userId), Tenant::query()->findOrFail($tenantId))?->name === TenantRole::Owner->value;
    }
}
