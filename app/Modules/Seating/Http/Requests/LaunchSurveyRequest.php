<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The last day people can answer the confidential survey of a center: tomorrow or later.
 */
final class LaunchSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['closes_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->addDay()->toDateString()]];
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
