<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The minutes of the Comisión where an assessment was recorded.
 */
final class RecordMinutesRequest extends FormRequest
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
        return ['document_id' => ['required', 'uuid']];
    }
}
