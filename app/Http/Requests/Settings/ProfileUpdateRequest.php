<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

final class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /** Emails are stored in lowercase, so uniqueness is checked on the lowercase address. */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        $user = $this->user();
        // A new address is a new way to sign in and to receive invitations: only the account's own password changes it.
        $changesEmail = $user === null || $this->string('email')->value() !== $user->email;

        return [
            ...$this->profileRules($user?->id),
            'current_password' => $changesEmail ? ['required', 'string', 'current_password'] : ['nullable'],
        ];
    }
}
