<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Data\OverrideData;
use App\Modules\Compensation\Support\SalaryRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** What a person really earns per day from a date on, as the form of the individual salaries sends it. */
final class OverrideRequest extends FormRequest
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
            'employee_number' => ['required', 'string', 'max:'.SalaryRules::NUMBER_LENGTH],
            'daily_salary' => ['required', 'string', new SalaryRule],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:'.SalaryRules::NOTE_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['employee_number' => 'ID de la empresa'];
    }

    public function toData(): OverrideData
    {
        return new OverrideData(
            $this->string('employee_number')->toString(),
            SalaryRules::pesos($this->string('daily_salary')->toString()) ?? 0,
            $this->date('effective_from', 'Y-m-d')?->toImmutable() ?? CarbonImmutable::now(),
            $this->filled('note') ? $this->string('note')->toString() : null,
        );
    }
}
