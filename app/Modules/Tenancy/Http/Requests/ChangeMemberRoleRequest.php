<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Http\Impersonation;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\In;

/**
 * The new role of a person: a template the account hands out or a role of the company's own. Who may change it is the controller's check.
 */
final class ChangeMemberRoleRequest extends FormRequest
{
    /** Whoever works as the owner changes a person only to a template: a role of the company's own is the owner's alone (404). */
    public function authorize(): bool
    {
        abort_if(resolve(Impersonation::class)->isActive() && MemberRoleRules::isCustom($this), 404);

        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<In|string>>
     */
    public function rules(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return ['role' => MemberRoleRules::role($tenant)];
    }

    /** The key of a template the account hands out or of a role of the company's own. */
    public function role(): string
    {
        return $this->string('role')->toString();
    }
}
