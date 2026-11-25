<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** The one «Guardar» of the brand dialog: a logo, if one was chosen, and whether the company has an accent of its own. */
final class UpdateTenantBrandRequest extends FormRequest
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
        // The logo's rules are those of UpdateTenantLogoRequest, but it may be left out: the logo the company has stays.
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', Rule::dimensions()->minHeight(40)->maxWidth(4000)->maxHeight(4000)];

        return [
            'logo' => $image,
            'logo_dark' => $image,
            'use_brand_color' => ['boolean'],
            'brand_color' => ['required_if_accepted:use_brand_color', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        // The version for dark mode shows only beside a logo for light: it is refused while the company would have none.
        return [function (Validator $validator): void {
            $tenant = $this->route('tenant');

            if ($this->hasFile('logo_dark') && ! $this->hasFile('logo') && $tenant instanceof Tenant && $tenant->logo_path === null) {
                $validator->errors()->add('logo_dark', 'Sube primero el logo para modo claro.');
            }
        }];
    }

    public function logoPath(): ?string
    {
        return $this->pathOf('logo');
    }

    public function darkLogoPath(): ?string
    {
        return $this->pathOf('logo_dark');
    }

    private function pathOf(string $field): ?string
    {
        $logo = $this->file($field);

        return $logo instanceof UploadedFile ? ($logo->getRealPath() ?: null) : null;
    }

    /** The accent of the company: the color picked while «usar color propio» is on, none (Humetría's) otherwise. */
    public function color(): ?string
    {
        $color = $this->validated('brand_color');

        return $this->boolean('use_brand_color') && is_string($color) ? $color : null;
    }
}
