<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\ReviewOfflineMark;
use Illuminate\Foundation\Http\FormRequest;

/** RH verifies the marks selected in the page of the queue, at most one page of them. */
final class VerifyOfflineMarksRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.ReviewOfflineMark::MAX_MARKS],
            'ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_filter($this->array('ids'), is_string(...)));
    }
}
