<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HR cancels an action: the reason is required, since nothing of the Programa is deleted.
 */
final class CancelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-interventions') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
