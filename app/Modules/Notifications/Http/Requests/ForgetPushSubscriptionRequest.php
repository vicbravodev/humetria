<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The phone gives its subscription up: it says which by its address. The session of the person is the credential. */
final class ForgetPushSubscriptionRequest extends FormRequest
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
        return ['endpoint' => ['required', 'string', 'max:2048']];
    }
}
