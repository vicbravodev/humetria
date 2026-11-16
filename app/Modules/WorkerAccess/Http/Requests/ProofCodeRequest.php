<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person without a key writes the code they got by email. Their session is the credential; whether they may prove with a
 * code (no key in use) is the action's to say.
 */
final class ProofCodeRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'regex:/^\d{6}$/']];
    }
}
