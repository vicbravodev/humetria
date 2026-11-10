<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\SurveyKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** The last day to answer, as a date; whether it is in range is the action's to say. */
final class LaunchSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kind = $this->route('kind');

        return is_string($kind) && $this->user()?->can(SurveyKind::from($kind)->managePermission()) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['closes_on' => ['required', 'date_format:Y-m-d']];
    }

    public function closesOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('closes_on')->toString()) ?? abort(422);
    }
}
