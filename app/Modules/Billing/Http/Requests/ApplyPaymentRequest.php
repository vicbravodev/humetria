<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ApplyPaymentRequest extends FormRequest
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
        return [
            'allocations' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && array_filter(array_keys($value), fn (int|string $key): bool => ! Str::isUuid((string) $key)) !== []) {
                    $fail('Elige estados de cuenta de la lista.');
                }
            }],
            'allocations.*' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && Money::parse($value) === null) {
                    $fail('Cada importe aplicado debe ser en pesos y con dos decimales a lo más, sin comas.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['allocations' => 'aplicación', 'allocations.*' => 'importe aplicado'];
    }

    /**
     * Cents by statement id, leaving out what was left blank.
     *
     * @return array<string, int>
     */
    public function allocations(): array
    {
        $allocations = [];

        foreach ($this->array('allocations') as $statementId => $pesos) {
            $cents = is_string($pesos) ? Money::parse($pesos) : null;

            if ($cents !== null && $cents > 0) {
                $allocations[(string) $statementId] = $cents;
            }
        }

        return $allocations;
    }
}
