<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class GrantModuleRequest extends FormRequest
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
            'module' => ['required', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['module' => 'módulo', 'reason' => 'motivo', 'until' => 'último día'];
    }

    public function until(): ?CarbonImmutable
    {
        return $this->filled('until') ? CarbonImmutable::parse($this->string('until')->toString())->startOfDay() : null;
    }
}
