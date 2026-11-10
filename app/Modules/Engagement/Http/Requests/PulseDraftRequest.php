<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** What the pulse looks at this time: one of the three modes. Whether it can be followed up is the action's to say. */
final class PulseDraftRequest extends FormRequest
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
        return ['mode' => ['required', 'string', Rule::enum(PulseMode::class)]];
    }

    public function mode(): PulseMode
    {
        return PulseMode::from($this->string('mode')->toString());
    }
}
