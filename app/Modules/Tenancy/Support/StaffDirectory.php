<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;

/** Humetría's own people, the ones a company can be handed to. A handful, so they come whole and by name. */
final class StaffDirectory
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function all(): array
    {
        return array_values(User::query()
            ->whereExists(fn (Builder $roles): Builder => $roles->from('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', [User::SUPERADMIN, User::SUPPORT])
                ->whereNull('model_has_roles.tenant_id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->whereColumn('model_has_roles.model_id', 'users.id'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->all());
    }
}
