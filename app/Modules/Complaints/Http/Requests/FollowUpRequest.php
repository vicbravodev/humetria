<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reference and the key of a complaint, sent in the body (never in the address), with the page of the thread asked for.
 * Errors say what is wrong and never echo what was written.
 */
final class FollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:40'],
            'key' => ['required', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function reference(): string
    {
        return $this->string('reference')->toString();
    }

    public function secret(): string
    {
        return $this->string('key')->toString();
    }
}
