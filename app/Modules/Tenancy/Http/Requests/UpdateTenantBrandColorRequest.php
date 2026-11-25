<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateTenantBrandColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The 404 of the gate: nobody else learns the company exists.
        Gate::authorize('manage-tenants');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/']];
    }

    public function color(): ?string
    {
        $color = $this->validated('brand_color');

        return is_string($color) ? $color : null;
    }
}
