<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use App\Modules\Campaigns\Support\CampaignClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guía I for chosen people of a work center, and the last day they can answer.
 */
final class TraumaCampaignRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_center_id' => ['required', 'uuid'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'employee_ids.*' => ['string', 'distinct'],
            'closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CampaignClock::today()->addDay()->format('Y-m-d')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Elige al menos a una persona.',
            'closes_on.after_or_equal' => 'La fecha de cierre debe ser a partir de mañana.',
        ];
    }

    public function workCenterId(): string
    {
        return $this->string('work_center_id')->toString();
    }

    /**
     * @return list<string>
     */
    public function employeeIds(): array
    {
        return array_values(array_filter($this->array('employee_ids'), is_string(...)));
    }

    public function closesOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('closes_on')->toString())->startOfDay();
    }
}
