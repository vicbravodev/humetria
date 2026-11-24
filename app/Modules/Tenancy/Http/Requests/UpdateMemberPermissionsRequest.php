<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * «Permisos adicionales»: what a person has besides their role, the whole list at once (none takes them all away).
 * Another company's person, or the owner, is not found here, before anything is validated.
 */
final class UpdateMemberPermissionsRequest extends FormRequest
{
    private ?TenantMembership $member = null;

    public function authorize(): bool
    {
        if (! Gate::allows('manage-members')) {
            return false;
        }

        $this->member();

        return true;
    }

    /**
     * @return array<string, list<(Closure(string, mixed, Closure(string): mixed): void)|string>>
     */
    public function rules(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', CompanyRoleRules::permission($tenant)],
            'acknowledge_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(#[RouteParameter('tenant')] Tenant $tenant, CompanyRoles $roles): array
    {
        $held = $roles->directPermissionsOf($this->member()->user()->sole(), $tenant);

        return [fn (Validator $validator) => CompanyRoleRules::acknowledged($validator, $this, $held)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return CompanyRoleRules::ATTRIBUTES;
    }

    /** The person, a member of the company who is not its owner. */
    public function member(): TenantMembership
    {
        if ($this->member !== null) {
            return $this->member;
        }

        $tenant = $this->route('tenant');
        $id = $this->route('member');
        $member = $tenant instanceof Tenant && is_string($id) ? $tenant->memberships()->find($id) : null;
        $role = $tenant instanceof Tenant && $member !== null ? resolve(CompanyRoles::class)->inCompany($tenant)[$member->user_id] ?? null : null;

        return $this->member = $member !== null && $role !== null && $role->key !== TenantRole::Owner->value ? $member : abort(404);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return CompanyRoleRules::names($this);
    }
}
