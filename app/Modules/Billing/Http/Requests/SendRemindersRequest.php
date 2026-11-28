<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SendRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * At most a page of the list (25), each account once.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'account_ids' => ['required', 'array', 'min:1', 'max:25'],
            'account_ids.*' => ['required', 'uuid', 'distinct', 'exists:billing_accounts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['account_ids' => 'cuentas', 'account_ids.*' => 'cuenta'];
    }

    /**
     * @return list<string>
     */
    public function accountIds(): array
    {
        return array_values(array_filter($this->array('account_ids'), is_string(...)));
    }
}
