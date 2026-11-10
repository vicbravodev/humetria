<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\PaymentData;
use App\Modules\Billing\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class RecordPaymentRequest extends FormRequest
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
        $pesos = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && Money::parse($value) === null) {
                $fail('Cada importe aplicado debe ser en pesos y con dos decimales a lo más, sin comas.');
            }
        };

        return [
            'amount' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $cents = is_string($value) ? Money::parse($value) : null;

                if ($cents === null || $cents < 1) {
                    $fail('El importe debe ser mayor que cero, en pesos y con dos decimales a lo más, sin comas.');
                }
            }],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'reference' => ['required', 'string', 'max:120', 'regex:/\A[^\r\n]*\z/u'],
            'note' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && array_filter(array_keys($value), fn (int|string $key): bool => ! Str::isUuid((string) $key)) !== []) {
                    $fail('Elige estados de cuenta de la lista.');
                }
            }],
            'allocations.*' => ['nullable', 'string', $pesos],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'importe', 'paid_on' => 'fecha del pago', 'reference' => 'referencia', 'note' => 'nota', 'allocations' => 'aplicación', 'allocations.*' => 'importe aplicado'];
    }

    public function toData(): PaymentData
    {
        $allocations = [];

        foreach ($this->array('allocations') as $statementId => $pesos) {
            $cents = is_string($pesos) ? Money::parse($pesos) : null;

            if ($cents !== null && $cents > 0) {
                $allocations[(string) $statementId] = $cents;
            }
        }

        return new PaymentData(
            Money::parse($this->string('amount')->toString()) ?? 0,
            CarbonImmutable::parse($this->string('paid_on')->toString())->startOfDay(),
            $this->string('reference')->toString(),
            $this->filled('note') ? $this->string('note')->toString() : null,
            $allocations,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => trim($this->string('amount')->toString()), 'reference' => trim($this->string('reference')->toString())]);
    }
}
