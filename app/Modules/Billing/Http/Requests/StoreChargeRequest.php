<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\ChargeData;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreChargeRequest extends FormRequest
{
    private const string SINGLE_LINE = 'regex:/\A[^\r\n]*\z/u';

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
        return [
            'kind' => ['required', Rule::enum(ChargeKind::class)],
            'description' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'scope' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'amount' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $cents = is_string($value) ? Money::parse($value) : null;

                if ($cents === null || $cents < 1) {
                    $fail('El importe debe ser mayor que cero, en pesos y con dos decimales a lo más, sin comas.');
                }
            }],
            'charge_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', Rule::prohibitedIf($this->string('kind')->toString() !== ChargeKind::Recurring->value), 'date_format:Y-m-d', 'after_or_equal:charge_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ends_on.prohibited' => 'Solo un cargo recurrente termina un día: uno de una sola vez se cobra una vez.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'importe', 'description' => 'descripción', 'scope' => 'alcance', 'charge_on' => 'fecha del cargo', 'ends_on' => 'fecha de término'];
    }

    public function toData(): ChargeData
    {
        return new ChargeData(
            kind: ChargeKind::from($this->string('kind')->toString()),
            description: $this->string('description')->toString(),
            scope: $this->string('scope')->toString(),
            amountCents: Money::parse($this->string('amount')->toString()) ?? 0,
            chargeOn: CarbonImmutable::parse($this->string('charge_on')->toString())->startOfDay(),
            endsOn: $this->filled('ends_on') ? CarbonImmutable::parse($this->string('ends_on')->toString())->startOfDay() : null,
        );
    }

    /** What to validate: no day given is today, where the panel shows it, and an empty end is none. */
    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        $this->merge([
            'charge_on' => $this->filled('charge_on') ? trim($this->string('charge_on')->toString()) : $today,
            'ends_on' => $this->filled('ends_on') ? trim($this->string('ends_on')->toString()) : null,
            'amount' => trim($this->string('amount')->toString()),
        ]);
    }
}
