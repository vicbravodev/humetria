<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person asks for a link to their email. Anyone may ask: the answer is the same whether the email is anyone's or not.
 */
final class RequestLinkRequest extends FormRequest
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
        return ['email' => ['required', 'string', 'email', 'max:254']];
    }

    public function email(): string
    {
        return $this->string('email')->trim()->toString();
    }
}
