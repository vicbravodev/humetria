<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person enters with the signature of their key. The signature is the credential and alone names the person; there is no
 * account to authorize.
 */
final class EnterRequest extends FormRequest
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
        return ['credential' => ['required', 'array']];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }
}
