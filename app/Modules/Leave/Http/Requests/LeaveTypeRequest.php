<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A leave type of the company's own: its name and whether it is paid. Owner and hr only. SaveLeaveType answers the rest:
 * a repeated name, a type of the law.
 */
final class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80'], 'paid' => ['nullable', 'boolean']];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function paid(): bool
    {
        return $this->boolean('paid');
    }
}
