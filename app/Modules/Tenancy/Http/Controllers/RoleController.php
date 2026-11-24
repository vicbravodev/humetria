<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Impersonation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * «Roles y permisos»: the templates of the system, locked, and the company's own roles. Only its owner manages them; the
 * staff working as the owner reads them and changes nothing (ForbidWhileImpersonating guards every change).
 */
final class RoleController extends Controller
{
    private const int PER_PAGE = 25;

    public function __construct(private readonly Impersonation $impersonation) {}

    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('manage-members');
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $people = $this->peopleByRole($tenant);
        $invitations = $this->openInvitationsByRole($tenant);
        $templates = Role::query()->withCount('permissions')->whereNull('tenant_id')
            ->whereIn('name', array_map(fn (TenantRole $role): string => $role->value, $tenant->type->assignableRoles()))
            ->get()->keyBy('name');

        return Inertia::render('tenancy/roles', [
            // The templates the account hands out, in their own order; the owner's is not handed out here.
            'templates' => array_map(fn (TenantRole $template): array => [
                'key' => $template->value,
                'label' => $template->label(),
                'people' => self::countOf($people, $templates->get($template->value)),
                'permissions' => $templates->get($template->value)?->getAttribute('permissions_count'),
            ], $tenant->type->assignableRoles()),
            'roles' => $this->own($tenant)
                ->withCount('permissions')
                ->when($search !== '', fn (Builder $query) => $query->where(new Expression('unaccent(label)'), 'ilike', '%'.addcslashes(Str::ascii($search), '\\%_').'%'))
                ->orderBy('label')
                ->orderBy('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (Role $role): array => [
                    'id' => $role->id,
                    'label' => $role->getAttribute('label'),
                    'based_on' => is_string($basedOn = $role->getAttribute('based_on')) ? TenantRole::tryFrom($basedOn)?->label() : null,
                    'people' => self::countOf($people, $role),
                    'invitations' => self::countOf($invitations, $role),
                    'permissions' => $role->getAttribute('permissions_count'),
                ]),
            'search' => $search,
            'limit' => ['used' => $this->own($tenant)->count(), 'max' => config()->integer('tenancy.max_custom_roles')],
            'can_manage' => ! $this->impersonation->isActive(),
        ]);
    }

    /**
     * The company's own roles: never a template nor another company's, so a role id from elsewhere is not found here.
     *
     * @return Builder<Role>
     */
    private function own(Tenant $tenant): Builder
    {
        return Role::query()->where('tenant_id', $tenant->id)->where('name', 'like', 'custom\_%');
    }

    /**
     * How many people of this company hold each role, by role id: a template's holders in other companies do not count.
     *
     * @return array<int, int>
     */
    private function peopleByRole(Tenant $tenant): array
    {
        return $this->countByRole(DB::table('model_has_roles')->where('tenant_id', $tenant->id));
    }

    /**
     * @return array<int, int>
     */
    private function openInvitationsByRole(Tenant $tenant): array
    {
        return $this->countByRole(DB::table('tenant_invitations')->where('tenant_id', $tenant->id)->whereNotNull('role_id')->whereNull('accepted_at')->whereNull('cancelled_at'));
    }

    /**
     * @param  array<int, int>  $counts
     */
    private static function countOf(array $counts, ?Role $role): int
    {
        return is_int($id = $role?->getKey()) ? $counts[$id] ?? 0 : 0;
    }

    /**
     * @return array<int, int>
     */
    private function countByRole(QueryBuilder $query): array
    {
        $counts = [];

        foreach ($query->groupBy('role_id')->get(['role_id', DB::raw('count(*) as total')]) as $row) {
            if (is_int($row->role_id) && is_int($row->total)) {
                $counts[$row->role_id] = $row->total;
            }
        }

        return $counts;
    }
}
