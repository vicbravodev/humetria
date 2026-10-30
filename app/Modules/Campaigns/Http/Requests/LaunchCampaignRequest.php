<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use App\Modules\Campaigns\Support\CampaignClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The last day people can answer: tomorrow at the earliest, in the display time zone.
 */
final class LaunchCampaignRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CampaignClock::today()->addDay()->format('Y-m-d')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['closes_on.after_or_equal' => 'La fecha de cierre debe ser a partir de mañana.'];
    }

    public function closesOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('closes_on')->toString())->startOfDay();
    }
}
