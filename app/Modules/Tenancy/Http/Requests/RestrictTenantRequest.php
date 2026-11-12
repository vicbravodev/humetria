<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class RestrictTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-tenants');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'restriction' => ['required', Rule::enum(TenantRestriction::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function restriction(): TenantRestriction
    {
        return TenantRestriction::from($this->string('restriction')->toString());
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
