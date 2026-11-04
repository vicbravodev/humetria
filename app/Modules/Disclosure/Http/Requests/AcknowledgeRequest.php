<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The confirmation of a person's link: which version they read. The link is the credential, so there is no account to ask.
 */
final class AcknowledgeRequest extends FormRequest
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
        return ['document' => ['required', 'uuid']];
    }

    public function documentId(): string
    {
        return $this->string('document')->toString();
    }
}
