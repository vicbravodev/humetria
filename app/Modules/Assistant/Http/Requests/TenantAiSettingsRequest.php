<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class TenantAiSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'monthly_token_budget' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function budget(): ?int
    {
        return $this->filled('monthly_token_budget') ? $this->integer('monthly_token_budget') : null;
    }
}
