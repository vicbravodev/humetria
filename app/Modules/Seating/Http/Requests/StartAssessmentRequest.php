<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The person whose assessment is opened. Whether they hold a position of the center is the action's to say.
 */
final class StartAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assess-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['employee_id' => ['required', 'uuid']];
    }
}
