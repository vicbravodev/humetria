<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Requests;

use App\Modules\Results\Data\ReferralRecord;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Enums\ReferralStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The channeling of a person who requires a clinical assessment (board V4-N035-Canalizacion, decisión A7): where to from a
 * closed list, how they were told, the day (not in the future), an optional note without clinical detail, whether to be
 * reminded in 30 days and, once it happened, that they were attended. Whoever may not see the list of those people does
 * not find this either (404, like the list).
 */
final class ScreeningReferralRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', Rule::enum(Destination::class)],
            'told' => ['required', Rule::enum(HowTold::class)],
            'referred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'note' => ['nullable', 'string', 'max:200'],
            'remind' => ['sometimes', 'boolean'],
            'attended' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('view-trauma-screenings') ?? false;
    }

    public function record(): ReferralRecord
    {
        $note = $this->string('note')->trim()->toString();

        return new ReferralRecord(
            $this->boolean('attended') ? ReferralStatus::Attended : ReferralStatus::Referred,
            Destination::from($this->string('destination')->toString()),
            HowTold::from($this->string('told')->toString()),
            $this->string('referred_on')->toString(),
            $note === '' ? null : $note,
            $this->boolean('remind'),
        );
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
