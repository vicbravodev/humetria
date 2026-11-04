<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The reference and the key of a complaint and the message the person writes in it. Errors never echo what was written. */
final class FollowUpMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:'.config()->integer('complaints.message_max'), 'regex:/\S/u'],
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

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }
}
