<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A means of disclosure RH records: where, how, which incisos of 5.7, on which day and a short note. Whether the center is in
 * use is the action's to judge.
 */
final class DisseminationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-disclosure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_center_id' => ['required', 'uuid'],
            'medium' => ['required', 'in:poster,bulletin,brochure,meeting,other'],
            'covered_items' => ['required', 'array', 'min:1'],
            'covered_items.*' => ['in:a,b,c,d,e,f'],
            'done_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return array_values(array_filter($this->array('covered_items'), is_string(...)));
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
