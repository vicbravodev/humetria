<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Whether someone works away from the work center more than 40 % of their time (board V2-Aplica). */
final class ApplicabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['has_teleworkers' => ['required', 'boolean']];
    }

    public function hasTeleworkers(): bool
    {
        return $this->boolean('has_teleworkers');
    }
}
