<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Support\Pulse\PulsePlanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** How often the pulse goes out, on which day, for how many days, and what the next one looks at. */
final class PulseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(SurveyKind::Pulse->managePermission()) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'frequency' => ['required', 'string', Rule::enum(PulseFrequency::class)],
            'day_of_month' => ['required', 'integer', 'between:1,'.PulsePlanner::LAST_DAY],
            'open_days' => ['required', 'integer', 'between:3,14'],
            'active' => ['required', 'boolean'],
            'next_mode' => ['nullable', 'string', Rule::enum(PulseMode::class)],
        ];
    }

    public function frequency(): PulseFrequency
    {
        return PulseFrequency::from($this->string('frequency')->toString());
    }

    public function nextMode(): ?PulseMode
    {
        return PulseMode::tryFrom($this->string('next_mode')->toString());
    }
}
