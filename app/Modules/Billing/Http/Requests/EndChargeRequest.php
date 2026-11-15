<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\SubscriptionCharge;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class EndChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $charge = $this->route('charge');

        return [
            'ends_on' => ['required', 'date_format:Y-m-d', ...($charge instanceof SubscriptionCharge ? ['after_or_equal:'.$charge->charge_on->toDateString()] : [])],
            'reason' => ['required', 'string', 'max:500', 'regex:/\A[^\r\n]*\z/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ends_on' => 'último día', 'reason' => 'motivo'];
    }

    public function endsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('ends_on')->toString())->startOfDay();
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ends_on' => trim($this->string('ends_on')->toString()), 'reason' => trim($this->string('reason')->toString())]);
    }
}
