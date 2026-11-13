<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** The platform's users for the console: who they are, their platform role, whether they use two-factor, and the companies they belong to. */
final class UserPages
{
    private const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, platformRole: string|null, companies: string, twoFactor: bool, verified: bool}>
     */
    public function handle(string $search): LengthAwarePaginator
    {
        $page = User::query()
            ->matching(['name', 'email'], $search)
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $ids = array_values($page->getCollection()->map(fn (User $user): int => $user->id)->all());
        $platform = $this->platformRoles($ids);
        $companies = $this->companies($ids);

        return $page->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'platformRole' => $platform[$user->id] ?? null,
            'companies' => implode(', ', $companies[$user->id] ?? []),
            'twoFactor' => $user->two_factor_confirmed_at !== null,
            'verified' => $user->email_verified_at !== null,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function platformRoles(array $ids): array
    {
        $held = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [User::SUPERADMIN, User::SUPPORT])
            ->whereNull('model_has_roles.tenant_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', $ids)
            ->get(['model_has_roles.model_id', 'roles.name']) as $row) {
            if (is_int($row->model_id) && is_string($row->name)) {
                $held[$row->model_id] = $row->name;
            }
        }

        return $held;
    }

    /**
     * "Acme (owner)" for each company the user holds a role in, in one query for the whole page.
     *
     * @param  list<int>  $ids
     * @return array<int, list<string>>
     */
    private function companies(array $ids): array
    {
        $held = [];

        foreach (DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('tenants', 'tenants.id', '=', 'model_has_roles.tenant_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', $ids)
            ->orderBy('tenants.trade_name')
            ->get(['model_has_roles.model_id', 'tenants.trade_name', 'roles.name']) as $row) {
            if (is_int($row->model_id) && is_string($row->trade_name) && is_string($row->name)) {
                $held[$row->model_id][] = "{$row->trade_name} ({$row->name})";
            }
        }

        return $held;
    }
}
