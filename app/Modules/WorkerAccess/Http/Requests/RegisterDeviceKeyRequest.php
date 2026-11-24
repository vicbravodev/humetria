<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The app registers the device key its phone made (plan B of the offline marks): the public key (SPKI in base64url), the challenge
 * it was given and its signature over the 32 raw bytes of it (possession), and a name for the phone. Only a person with a session
 * may; the person comes from the session, never from the body.
 */
final class RegisterDeviceKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return resolve(WorkerSession::class)->current() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
            'challenge' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'signature' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            // Plain characters only, as the name of a key: it is shown to the person.
            'label' => ['required', 'string', 'max:60', 'regex:/^[\pL\pN .\-_()]+$/u'],
        ];
    }
}
