<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A new role of the company's own, blank or copied from a template the account hands out. */
final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<(Closure(string, mixed, Closure(string): mixed): void)|In|string>>
     */
    public function rules(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return [
            ...CompanyRoleRules::rules($tenant),
            'based_on' => ['nullable', 'string', Rule::in(array_map(fn (TenantRole $role): string => $role->value, $tenant->type->assignableRoles()))],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return [fn (Validator $validator) => CompanyRoleRules::after($validator, $this, $tenant)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return CompanyRoleRules::ATTRIBUTES;
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

    public function basedOn(): ?TenantRole
    {
        return TenantRole::tryFrom($this->string('based_on')->toString());
    }
}
