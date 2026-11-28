<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Who may own a demo request: Humetría's own people (superadmin or support), never someone of a company. */
final class DemoOwners
{
    /** @return list<array{id: int, name: string}> */
    public function options(): array
    {
        return array_values($this->query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all());
    }

    public function has(int $userId): bool
    {
        return $this->query()->whereKey($userId)->exists();
    }

    /** @return Builder<User> */
    private function query(): Builder
    {
        return User::query()->whereIn('id', DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [User::SUPERADMIN, User::SUPPORT])
            ->whereNull('model_has_roles.tenant_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->select('model_has_roles.model_id'));
    }
}
