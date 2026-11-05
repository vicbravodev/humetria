<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Support\BenefitValue;
use App\Modules\Compensation\Support\TenureTable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The terms of a benefit from a date on: a value and, when it grows with seniority, its table by years of service. */
final class TermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-compensation');
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'value' => ['required', 'string', new AmountRule],
            'tenure_table' => ['nullable', 'array', 'max:'.TenureTable::MAX_ROWS],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->table() === null) {
                $validator->errors()->add('tenure_table', 'Cada renglón lleva años de servicio, un número entero, y un valor como 12 o 12.5.');
            }
        }];
    }

    public function toData(): TermData
    {
        return new TermData(
            $this->date('effective_from', 'Y-m-d')?->toImmutable() ?? CarbonImmutable::now(),
            BenefitValue::parse($this->string('value')->toString()) ?? 0,
            $this->table() ?: null,
        );
    }

    /**
     * The rows of the table as numbers; null when one is not a whole number of years and a value.
     *
     * @return ?list<array{from_years: int, value: int}>
     */
    private function table(): ?array
    {
        $rows = [];

        foreach ($this->array('tenure_table') as $row) {
            $years = is_array($row) && is_string($row['from_years'] ?? null) ? $row['from_years'] : '';
            $value = is_array($row) && is_string($row['value'] ?? null) ? BenefitValue::parse($row['value']) : null;

            if (preg_match('/\A\d{1,3}\z/', $years) !== 1 || $value === null) {
                return null;
            }

            $rows[] = ['from_years' => (int) $years, 'value' => $value];
        }

        return $rows;
    }
}
