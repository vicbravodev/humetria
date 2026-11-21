<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\CorrectMarkDirectly;
use Illuminate\Foundation\Http\FormRequest;

/** RH corrects a mark from the report: its new local time, or voided, and always a note. */
final class CorrectMarkRequest extends FormRequest
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
            'void' => ['sometimes', 'boolean'],
            'at' => ['exclude_if:void,true', 'required', 'date_format:Y-m-d H:i'],
            'note' => ['required', 'string', 'min:'.CorrectMarkDirectly::MIN_NOTE, 'max:500'],
        ];
    }

    /** The new local time; null voids the mark. */
    public function at(): ?string
    {
        return $this->boolean('void') ? null : $this->string('at')->toString();
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }
}
