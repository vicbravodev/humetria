<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** The platform's users for the console: who they are, their platform role, whether they use two-factor, and the companies they belong to. */
final class UserPages
{
    private const int PER_PAGE = 25;

    /** The quick help of the team view: a few people at a time, beside the team. */
    public const int HELP_PER_PAGE = 5;

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, platformRole: string|null, companies: string, twoFactor: bool, verified: bool, lastLogin: string|null, supportEntries: int}>
     */
    public function handle(string $search, bool $staffOnly = false, int $perPage = self::PER_PAGE, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = $this->matching($search, $staffOnly)
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, pageName: $pageName)
            ->withQueryString();

        $ids = array_values($page->getCollection()->map(fn (User $user): int => $user->id)->all());
        $platform = $this->platformRoles($ids);
        $companies = $this->companies($ids);
        $entries = $this->supportEntries($ids);

        return $page->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'platformRole' => $platform[$user->id] ?? null,
            'companies' => implode(', ', $companies[$user->id] ?? []),
            'twoFactor' => $user->two_factor_confirmed_at !== null,
            'verified' => $user->email_verified_at !== null,
            'lastLogin' => $user->last_login_at === null ? null : DisplayDate::day($user->last_login_at),
            'supportEntries' => $entries[$user->id] ?? 0,
        ]);
    }

    /**
     * How many times each person entered a company as its owner in the last 30 days, as the audit trail recorded it.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function supportEntries(array $ids): array
    {
        $entries = [];

        foreach (AuditEntry::query()
            ->toBase()
            ->where('event', 'impersonation.started')
            ->whereIn('actor_id', $ids)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('actor_id')
            ->get(['actor_id', DB::raw('count(*) as entries')]) as $row) {
            if (is_int($row->actor_id) && is_int($row->entries)) {
                $entries[$row->actor_id] = $row->entries;
            }
        }

        return $entries;
    }

    /**
     * How many each view holds: Humetría's staff, always whole, and everyone with an account the search finds.
     *
     * @return array{team: int, people: int}
     */
    public function counts(string $search): array
    {
        return ['team' => $this->matching('', true)->count(), 'people' => $this->matching($search, false)->count()];
    }

    /** @return Builder<User> */
    private function matching(string $search, bool $staffOnly): Builder
    {
        return User::query()
            ->matching(['name', 'email'], $search)
            ->when($staffOnly, fn (Builder $query) => $query->whereExists(fn (QueryBuilder $roles) => $roles
                ->from('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', [User::SUPERADMIN, User::SUPPORT])
                ->whereNull('model_has_roles.tenant_id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->whereColumn('model_has_roles.model_id', 'users.id')));
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
