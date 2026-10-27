<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ActivateTenantRequest extends FormRequest
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
            'active_until' => ['required', 'bail', 'date_format:Y-m-d', 'after:'.now(config()->string('app.display_timezone'))->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['active_until.after' => 'Elige una fecha a partir de mañana.'];
    }

    /**
     * The end of the chosen day in Mexico City time, as the UTC instant that gets stored.
     */
    public function activeUntil(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('active_until')->toString(), config()->string('app.display_timezone'))->endOfDay()->utc();
    }
}
