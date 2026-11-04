<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Data\BenefitData;
use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Support\BenefitValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The form of a benefit the company makes up: its attributes and, when it is created, its first term (value and vigencia).
 * Amounts are typed in pesos or in the unit of the kind and read as hundredths without floats (BenefitValue).
 */
final class BenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-compensation');
    }

    /** Valid UTF-8 without control characters, as every single-line field of the app. */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    /**
     * @return array<string, list<In|string|ValidationRule>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => ['required', 'string', 'max:120', self::SINGLE_LINE],
            'kind' => ['required', 'string', Rule::in(array_column(BenefitKind::cases(), 'value'))],
            'frequency' => ['required', 'string', Rule::in(array_column(Frequency::cases(), 'value'))],
            'group' => ['nullable', 'string', Rule::in(array_column(PayGroup::cases(), 'value'))],
            'job_grade_ids' => ['nullable', 'array', 'max:100'],
            'min_tenure_months' => ['required', 'integer', 'min:0', 'max:1200'],
            'sbc_integration' => ['required', 'string', Rule::in(array_column(SbcIntegration::cases(), 'value'))],
            'sbc_cap' => ['nullable', 'string', new AmountRule],
            'cap' => ['nullable', 'string', new AmountRule],
            'value' => $creating ? ['required', 'string', new AmountRule] : ['prohibited'],
            'effective_from' => $creating ? ['required', 'date_format:Y-m-d'] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'tipo de cálculo'];
    }

    public function toData(): BenefitData
    {
        $amount = fn (string $key): ?int => $this->filled($key) ? BenefitValue::parse($this->string($key)->toString()) : null;
        $id = $this->route('benefit');

        return new BenefitData(
            id: is_string($id) ? $id : null,
            name: $this->string('name')->toString(),
            kind: BenefitKind::from($this->string('kind')->toString()),
            frequency: Frequency::from($this->string('frequency')->toString()),
            group: PayGroup::tryFrom($this->string('group')->toString()),
            jobGradeIds: array_values(array_filter(array_map(fn (mixed $id): string => is_string($id) ? $id : '', $this->array('job_grade_ids')), fn (string $id): bool => $id !== '')),
            minTenureMonths: $this->integer('min_tenure_months'),
            sbcIntegration: SbcIntegration::from($this->string('sbc_integration')->toString()),
            sbcCapCents: $amount('sbc_cap'),
            inPayrollTax: $this->boolean('in_payroll_tax'),
            inPtuBase: $this->boolean('in_ptu_base'),
            inSeniorityBase: $this->boolean('in_seniority_base'),
            incomeTaxExempt: $this->boolean('income_tax_exempt'),
            capCents: $amount('cap'),
        );
    }

    /** The first term, when the benefit is being created. */
    public function firstTerm(): ?TermData
    {
        $date = $this->date('effective_from', 'Y-m-d');
        $value = BenefitValue::parse($this->string('value')->toString());

        return $date === null || $value === null ? null : new TermData($date->toImmutable(), $value);
    }
}
