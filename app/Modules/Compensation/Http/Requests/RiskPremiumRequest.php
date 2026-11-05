<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Support\PremiumRate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The IMSS work risk premium of the company from a date on, typed as a percent ("7.58875"). */
final class RiskPremiumRequest extends FormRequest
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
            'rate' => ['required', 'string', new PremiumRule],
        ];
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return $this->date('effective_from', 'Y-m-d')?->toImmutable() ?? CarbonImmutable::now();
    }

    public function rate(): int
    {
        return PremiumRate::parse($this->string('rate')->toString()) ?? 0;
    }
}
