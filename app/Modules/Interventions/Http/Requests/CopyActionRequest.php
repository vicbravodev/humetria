<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HR copies an action to other rows of the Programa. The rows must be of the same Programa: the action checks it.
 */
final class CopyActionRequest extends FormRequest
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
        return [
            'item_ids' => ['required', 'array', 'min:1', 'max:20'],
            'item_ids.*' => ['uuid'],
        ];
    }

    /**
     * @return list<string>
     */
    public function itemIds(): array
    {
        return array_values(array_filter($this->array('item_ids'), is_string(...)));
    }
}
