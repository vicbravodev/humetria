<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\MemberLookup;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class CompanyMemberLookup implements MemberLookup
{
    public function userIdByEmail(string $tenantId, string $email): ?int
    {
        $id = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('user', fn (Builder $user) => $user->whereRaw('lower(email) = ?', [Str::lower($email)]))
            ->value('user_id');

        return is_int($id) ? $id : null;
    }
}
