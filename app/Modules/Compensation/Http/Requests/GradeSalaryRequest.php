<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Data\GradeSalaryData;
use App\Modules\Compensation\Support\SalaryRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The daily salary of a category and level from a date on, as the tabulator's form sends it. */
final class GradeSalaryRequest extends FormRequest
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
            'category' => ['required', 'string', 'max:'.SalaryRules::CATEGORY_LENGTH],
            'level' => ['required', 'string', 'max:'.SalaryRules::LEVEL_LENGTH],
            'daily_salary' => ['required', 'string', new SalaryRule],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): GradeSalaryData
    {
        return new GradeSalaryData(
            $this->string('category')->toString(),
            $this->string('level')->toString(),
            SalaryRules::pesos($this->string('daily_salary')->toString()) ?? 0,
            $this->date('effective_from', 'Y-m-d')?->toImmutable() ?? CarbonImmutable::now(),
        );
    }
}
