<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;

/**
 * Inviting someone into the company with a role. Who may do it is the controller's check; this one only validates.
 */
final class InviteMemberRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', TenantRules::SINGLE_LINE],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => MemberRoleRules::role(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(#[RouteParameter('tenant')] Tenant $tenant): array
    {
        return [function (Validator $validator) use ($tenant): void {
            if ($validator->errors()->has('email')) {
                return;
            }

            $email = $this->string('email')->toString();

            if ($tenant->memberships()->whereHas('user', fn (Builder $user) => $user->whereRaw('lower(email) = ?', [$email]))->exists()) {
                $validator->errors()->add('email', 'Esa persona ya tiene acceso a esta empresa.');
            } elseif ($tenant->invitations()->open()->where('role', '<>', TenantRole::Owner)->where('email', $email)->exists()) {
                $validator->errors()->add('email', 'Ya hay una invitación abierta para ese correo.');
            }
        }];
    }

    public function toData(): MemberInvitationData
    {
        return new MemberInvitationData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            role: TenantRole::from($this->string('role')->toString()),
        );
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower($email)]);
        }
    }
}
