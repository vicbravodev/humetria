<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\ReviewPeriod;
use Illuminate\Foundation\Http\FormRequest;

final class TeleworkSettingsRequest extends FormRequest
{
    /** Longest consent text, in characters. */
    public const int MAX_CONSENT = 20000;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_months' => ['required', 'integer', 'min:'.ReviewPeriod::MIN, 'max:'.ReviewPeriod::MAX],
            'consent_text' => ['nullable', 'string', 'max:'.self::MAX_CONSENT],
        ];
    }

    public function periodMonths(): int
    {
        return $this->integer('period_months');
    }

    public function consentText(): ?string
    {
        return $this->string('consent_text')->toString() ?: null;
    }
}
