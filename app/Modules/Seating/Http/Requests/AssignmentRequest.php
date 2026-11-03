<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The people RH puts in a position. Whether they are active in its center is the action's to say.
 */
final class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'employee_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function employeeIds(): array
    {
        return array_values(array_filter($this->array('employee_ids'), is_string(...)));
    }
}
