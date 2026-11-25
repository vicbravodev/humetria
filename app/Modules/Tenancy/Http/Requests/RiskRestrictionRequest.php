<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Suspending or archiving from the risk zone of a company's page: the reason, and its trade name typed, so nobody stops a
 * company by a slip of the hand. Read only stays in the list's menu, which does not ask for the name.
 */
final class RiskRestrictionRequest extends FormRequest
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
        $tenant = $this->route('tenant');
        $name = $tenant instanceof Tenant ? $tenant->trade_name : '';

        return [
            'restriction' => ['required', Rule::in([TenantRestriction::Suspended->value, TenantRestriction::Archived->value])],
            'reason' => ['required', 'string', 'max:500'],
            'confirmation' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($name): void {
                if (! is_string($value) || trim($value) !== $name) {
                    $fail('Escribe el nombre de la empresa tal como aparece: '.$name.'.');
                }
            }],
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
