<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Data\TenantData;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @phpstan-import-type FieldRules from TenantRules
 */
final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-tenants');

        return true;
    }

    /**
     * @return array<string, FieldRules>
     */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $own = $tenant instanceof Tenant ? $tenant : null;

        return [
            ...TenantRules::names(),
            'rfc' => TenantRules::rfc($own),
            'slug' => TenantRules::slug($own),
        ];
    }

    public function toData(): TenantData
    {
        return new TenantData(
            legalName: $this->string('legal_name')->toString(),
            tradeName: $this->string('trade_name')->toString(),
            rfc: $this->string('rfc')->toString(),
            slug: $this->string('slug')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(TenantRules::normalize($this->all()));
    }
}
