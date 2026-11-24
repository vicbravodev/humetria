<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyRoles;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Renaming a role of the company's own and changing what it has. A template or another company's role is not found
 * here, before anything is validated: the same 404 as a role that does not exist.
 */
final class UpdateRoleRequest extends FormRequest
{
    private ?Role $role = null;

    public function authorize(): bool
    {
        if (! Gate::allows('manage-members')) {
            return false;
        }

        $this->role();

        return true;
    }

    /**
     * @return array<string, list<(Closure(string, mixed, Closure(string): mixed): void)|string>>
     */
    public function rules(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return CompanyRoleRules::rules($tenant);
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        $held = $this->role()->permissions->map(fn (Permission $permission): string => $permission->name)->values()->all();

        return [fn (Validator $validator) => CompanyRoleRules::after($validator, $this, $tenant, $this->role(), $held)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return CompanyRoleRules::ATTRIBUTES;
    }

    /** The role being edited, one of the company's own. */
    public function role(): Role
    {
        $tenant = $this->route('tenant');
        $id = $this->route('role');

        return $this->role ??= $tenant instanceof Tenant && is_string($id) ? resolve(CompanyRoles::class)->own($tenant, $id) : abort(404);
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return CompanyRoleRules::names($this);
    }
}
