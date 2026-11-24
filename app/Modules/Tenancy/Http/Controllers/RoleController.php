<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Impersonation;
use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\DeleteCompanyRole;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\CompanyRoleInUse;
use App\Modules\Tenancy\Exceptions\CompanyRoleLimitReached;
use App\Modules\Tenancy\Http\Requests\CompanyRoleRules;
use App\Modules\Tenancy\Http\Requests\StoreRoleRequest;
use App\Modules\Tenancy\Http\Requests\UpdateRoleRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use App\Modules\Tenancy\Support\PermissionGroups;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function __construct(private readonly Impersonation $impersonation, private readonly CompanyRoles $roles) {}

    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('manage-members');
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $people = $this->peopleByRole($tenant);
        $invitations = $this->openInvitationsByRole($tenant);
        $templates = Role::query()->with('permissions')->whereNull('tenant_id')
            ->whereIn('name', array_map(fn (TenantRole $role): string => $role->value, $tenant->type->assignableRoles()))
            ->get()->keyBy('name');

        return Inertia::render('tenancy/roles', [
            // The templates the account hands out, in their own order; the owner's is not handed out here.
            'templates' => array_map(fn (TenantRole $template): array => [
                'key' => $template->value,
                'label' => $template->label(),
                'people' => self::countOf($people, $templates->get($template->value)),
                'permissions' => self::names($templates->get($template->value)),
            ], $tenant->type->assignableRoles()),
            'roles' => $this->roles->ownRoles($tenant)
                ->with('permissions')
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
                    'permissions' => self::names($role),
                ]),
            'search' => $search,
            // Which tools the plan has: what each role lets do is said only of those.
            'groups' => PermissionGroups::for($tenant, []),
            'limit' => ['used' => $this->roles->ownRoles($tenant)->count(), 'max' => config()->integer('tenancy.max_custom_roles')],
            'can_manage' => ! $this->impersonation->isActive(),
        ]);
    }

    /** «Nuevo rol»: blank, or (`?from=`) with what a template the account hands out has switched on. */
    public function create(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('manage-members');
        $from = TenantRole::tryFrom($request->string('from')->toString());
        $from = in_array($from, $tenant->type->assignableRoles(), true) ? $from : null;

        return Inertia::render('tenancy/role-editor', [
            'role' => null,
            'from' => $from?->value,
            'templates' => $this->templateOptions($tenant),
            'groups' => PermissionGroups::for($tenant, $from === null ? [] : self::held($this->roles->template($from))),
            'can_manage' => ! $this->impersonation->isActive(),
        ]);
    }

    public function store(StoreRoleRequest $request, Tenant $tenant, CreateCompanyRole $createCompanyRole, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $createCompanyRole->handle($tenant, $request->label(), $request->permissions(), $request->basedOn(), $user);
        } catch (CompanyRoleLimitReached) {
            $max = config()->integer('tenancy.max_custom_roles');

            throw ValidationException::withMessages(['label' => "Ya tienes {$max} roles propios, el máximo. Borra uno que no uses para crear otro."]);
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }

        return $this->done($tenant, 'Rol creado.');
    }

    public function edit(Tenant $tenant, string $role): Response
    {
        Gate::authorize('manage-members');
        $editing = $this->roles->own($tenant, $role);
        $id = $editing->getKey();

        return Inertia::render('tenancy/role-editor', [
            'role' => [
                'id' => $id,
                'label' => $editing->getAttribute('label'),
                'based_on' => is_string($basedOn = $editing->getAttribute('based_on')) ? TenantRole::tryFrom($basedOn)?->label() : null,
                'people' => count($this->roles->holders($editing)),
                'invitations' => $tenant->invitations()->open()->where('role_id', $id)->count(),
            ],
            'from' => null,
            'templates' => $this->templateOptions($tenant),
            'groups' => PermissionGroups::for($tenant, self::held($editing)),
            'can_manage' => ! $this->impersonation->isActive(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Tenant $tenant, UpdateCompanyRole $updateCompanyRole, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $updateCompanyRole->handle($request->role(), $request->label(), $request->permissions(), $user);
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }

        return $this->done($tenant, 'Rol actualizado.');
    }

    /**
     * The templates a new role can start from, each with what it switches on (the cards of «Empezar desde» set it).
     *
     * @return list<array{key: string, label: string, permissions: list<string>}>
     */
    private function templateOptions(Tenant $tenant): array
    {
        return array_map(function (TenantRole $template): array {
            return ['key' => $template->value, 'label' => $template->label(), 'permissions' => self::names($this->roles->template($template))];
        }, $tenant->type->assignableRoles());
    }

    /** Another role took the name between the check and the write (the database keeps names unique per company). */
    private static function nameTaken(): ValidationException
    {
        return ValidationException::withMessages(['label' => CompanyRoleRules::NAME_TAKEN]);
    }

    /** A role that someone holds, or an invitation not yet accepted names (expired ones too), stays. */
    public function destroy(Tenant $tenant, string $role, DeleteCompanyRole $deleteCompanyRole, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('manage-members');

        try {
            $deleteCompanyRole->handle($this->roles->own($tenant, $role), $user);
        } catch (CompanyRoleInUse) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No puedes borrar un rol que tiene personas o invitaciones sin aceptar, aunque hayan vencido. Pásalas a otro rol o cancela esas invitaciones primero.']);

            return redirect()->route('tenant.roles.index', $tenant);
        }

        return $this->done($tenant, 'Rol borrado.');
    }

    /**
     * What the role switches on, by name and in order.
     *
     * @return list<string>
     */
    private static function names(?Role $role): array
    {
        $names = $role === null ? [] : array_keys(self::held($role));
        sort($names);

        return $names;
    }

    /**
     * What the role has switched on, as the editor shows it.
     *
     * @return array<string, 'role'>
     */
    private static function held(Role $role): array
    {
        $held = [];

        foreach ($role->permissions as $permission) {
            $held[$permission->name] = 'role';
        }

        return $held;
    }

    private function done(Tenant $tenant, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('tenant.roles.index', $tenant);
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
