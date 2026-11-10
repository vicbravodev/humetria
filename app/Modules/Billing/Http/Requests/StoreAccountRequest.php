<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\AccountKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreAccountRequest extends FormRequest
{
    use BuildsAccountData;

    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['kind' => AccountRules::kind(), ...AccountRules::rules()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['kind' => 'tipo de cuenta'];
    }

    public function kind(): AccountKind
    {
        return AccountKind::from($this->string('kind')->toString());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(AccountRules::normalize($this->all()));
    }
}
