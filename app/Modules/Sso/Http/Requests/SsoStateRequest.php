<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Requests;

use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SsoStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-members');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'enforced' => ['required', 'boolean']];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(CurrentTenant $tenant): array
    {
        return [function (Validator $validator) use ($tenant): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ready = SsoConnection::query()->where('tenant_id', $tenant->id())->exists()
                && SsoDomain::query()->where('tenant_id', $tenant->id())->whereNotNull('verified_at')->exists();

            if ($this->boolean('enabled') && ! $ready) {
                $validator->errors()->add('enabled', 'Guarda el proveedor y verifica al menos un dominio antes de activar el SSO.');
            }

            if ($this->boolean('enforced') && ! $this->boolean('enabled')) {
                $validator->errors()->add('enforced', 'Activa el SSO antes de exigirlo.');
            }
        }];
    }
}
