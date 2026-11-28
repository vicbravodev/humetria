<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Companies chosen in the console's list, at most a page of them (25: nobody chooses more than they see), for an action on all
 * of them at once. Only the superadmin.
 *
 * @phpstan-require-extends FormRequest
 */
trait ChoosesCompanies
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
    protected function selectionRules(): array
    {
        return [
            'companies' => ['required', 'array', 'min:1', 'max:25'],
            'companies.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['companies' => 'empresas', 'user_id' => 'responsable'];
    }

    /**
     * @return list<string>
     */
    public function companies(): array
    {
        return array_values(array_filter($this->array('companies'), is_string(...)));
    }
}
