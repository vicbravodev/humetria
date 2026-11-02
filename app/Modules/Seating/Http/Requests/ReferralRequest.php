<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A referral to medical attention, or «evaluated, no referral»: the answer, the date and a short note without clinical
 * detail. Whether the user sits on the center's Comisión is the controller's to say.
 */
final class ReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true || $this->user()?->can('assess-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'referred' => ['required', 'boolean'],
            'referred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
