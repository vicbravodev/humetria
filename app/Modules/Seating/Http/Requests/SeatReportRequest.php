<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The report of a damaged seat from a person's portal: what is wrong with it, in their words.
 */
final class SeatReportRequest extends FormRequest
{
    /** The portal link is the credential: the controller opens it. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:1000']];
    }

    public function description(): string
    {
        return trim($this->string('description')->toString());
    }
}
