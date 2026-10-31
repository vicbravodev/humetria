<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\In;

/**
 * The new role of a person: hr or viewer. Who may change it is the controller's check.
 */
final class ChangeMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<In|string>>
     */
    public function rules(): array
    {
        return ['role' => MemberRoleRules::role()];
    }

    public function role(): TenantRole
    {
        return TenantRole::from($this->string('role')->toString());
    }
}
