<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateAccountRequest extends FormRequest
{
    use BuildsAccountData;

    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * The kind is not here: an account never changes it, and a form that sends one is ignored.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return AccountRules::rules($account instanceof BillingAccount ? $account : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(AccountRules::normalize($this->all()));
    }
}
