<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RH answers or cancels a request: owner and hr only. Rejecting needs its reason, since the person reads it.
 */
final class DecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->routeIs('tenant.vacations.requests.reject') ? ['reason' => ['required', 'string', 'min:5', 'max:500']] : [];
    }

    /** The user of RH who answers. */
    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
