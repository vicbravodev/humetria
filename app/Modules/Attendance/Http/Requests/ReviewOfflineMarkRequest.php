<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\ReviewOfflineMark;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RH verifies a mark made without a connection, with a note if they want, or says it is incorrect: with the note the person
 * reads and the local time that is right (empty: the mark is voided).
 */
final class ReviewOfflineMarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rejecting = $this->rejecting();

        return [
            'note' => [$rejecting ? 'required' : 'nullable', 'string', 'min:'.ReviewOfflineMark::MIN_NOTE, 'max:500'],
            'corrected_at' => [$rejecting ? 'nullable' : 'prohibited', 'date_format:Y-m-d H:i'],
        ];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }

    /** The local time that is right, in the zone of the mark; null voids it. */
    public function correctedAt(): ?string
    {
        return $this->filled('corrected_at') ? $this->string('corrected_at')->toString() : null;
    }

    private function rejecting(): bool
    {
        return $this->route()?->getName() === 'tenant.attendance.offline.reject';
    }
}
