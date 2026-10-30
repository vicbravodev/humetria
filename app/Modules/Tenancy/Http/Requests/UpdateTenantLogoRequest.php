<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateTenantLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The 404 of the gate: nobody else learns the company exists.
        Gate::authorize('manage-tenants');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        // No SVG: mail clients do not show it, and it can carry a script.
        return [
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', Rule::dimensions()->minHeight(40)],
        ];
    }

    public function logoPath(): string
    {
        $logo = $this->file('logo');

        return $logo instanceof UploadedFile ? $logo->getRealPath() : '';
    }
}
