<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The person RH sends a link to link a device: owner and hr only. Whether they are the company's is the action's to say. */
final class EnrollPersonRequest extends FormRequest
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
        return ['employee_id' => ['required', 'uuid']];
    }

    public function employeeId(): string
    {
        return $this->string('employee_id')->toString();
    }
}
