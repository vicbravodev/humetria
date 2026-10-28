<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Data\OwnerInvitationData;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-tenants');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $tenant = $this->route('tenant');

            if ($validator->errors()->has('email') || ! $tenant instanceof Tenant) {
                return;
            }

            $isOwner = $tenant->ownerMembership()
                ->whereHas('user', fn (Builder $user) => $user->whereRaw('lower(email) = ?', [$this->string('email')->toString()]))
                ->exists();

            if ($isOwner) {
                $validator->errors()->add('email', 'Esta persona ya es el owner.');
            }
        }];
    }

    public function toData(): OwnerInvitationData
    {
        return new OwnerInvitationData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        // The TrimStrings middleware already took the spaces off.
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower($email)]);
        }
    }
}
