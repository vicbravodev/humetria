<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\AdjustmentData;
use App\Modules\Billing\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class AdjustStatementRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:200', self::SINGLE_LINE],
            'reason' => ['required', 'string', 'max:500', self::SINGLE_LINE],
            'amount' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $cents = is_string($value) ? self::cents($value) : null;

                if ($cents === null || $cents === 0) {
                    $fail('El importe debe ser distinto de cero, en pesos y con dos decimales a lo más, sin comas; con un signo menos si es un crédito.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['description' => 'descripción', 'reason' => 'motivo', 'amount' => 'importe'];
    }

    public function toData(): AdjustmentData
    {
        return new AdjustmentData($this->string('description')->toString(), self::cents($this->string('amount')->toString()) ?? 0, $this->string('reason')->toString());
    }

    /** Pesos typed as "149.50" or, for a credit, "-149.50", as cents; null for anything else. */
    private static function cents(string $pesos): ?int
    {
        $pesos = trim($pesos);
        $credit = str_starts_with($pesos, '-');
        $cents = Money::parse($credit ? substr($pesos, 1) : $pesos);

        return $cents === null ? null : ($credit ? -$cents : $cents);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => trim($this->string('amount')->toString())]);
    }
}
