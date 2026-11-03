<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How RH resolved a seat report.
 */
final class ResolveSeatReportRequest extends FormRequest
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
        return ['resolution' => ['required', 'string', 'max:1000']];
    }

    public function resolution(): string
    {
        return trim($this->string('resolution')->toString());
    }
}
