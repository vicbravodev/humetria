<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person registers the key their device made, with a name for the device. Their session is the credential; whether it may
 * register a key is the controller's to say, before anything is read.
 */
final class RegisterKeyRequest extends FormRequest
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
            // Plain characters only: the name goes into an email line.
            'label' => ['required', 'string', 'max:60', 'regex:/^[\pL\pN .\-_()]+$/u'],
            'credential' => ['required', 'array'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }
}
