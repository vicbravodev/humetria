<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Modules\Tenancy\Data\NewAccountData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    public function toData(): NewAccountData
    {
        return new NewAccountData(
            name: $this->string('name')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
