<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Requests;

use App\Modules\Results\Enums\ReferralStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The channeling of a person who requires a clinical assessment: referred or attended, the day (not in the future) and
 * the institution. Whoever may not see the list of those people does not find this either (404, like the list).
 */
final class ScreeningReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-trauma-screenings') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReferralStatus::class)],
            'referred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'institution' => ['required', 'string', 'max:150'],
        ];
    }

    public function status(): ReferralStatus
    {
        return ReferralStatus::from($this->string('status')->toString());
    }

    public function institution(): string
    {
        return $this->string('institution')->toString();
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
