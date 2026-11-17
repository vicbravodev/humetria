<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** "Entrar" from the console: as the owner, or (only in the demo company) as its HR. */
final class ImpersonateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not on the staff must not even learn the body was invalid.
        Gate::authorize('view-console');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['as' => ['sometimes', 'string', 'in:owner,hr']];
    }

    public function role(): TenantRole
    {
        return $this->string('as')->toString() === 'hr' ? TenantRole::Hr : TenantRole::Owner;
    }
}
