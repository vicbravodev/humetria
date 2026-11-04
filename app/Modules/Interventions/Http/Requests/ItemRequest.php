<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use App\Modules\Interventions\Enums\ItemScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HR adds a row to the Programa, chosen from the catalog of the guide (the action checks the choice).
 */
final class ItemRequest extends FormRequest
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
            'scope' => ['required', Rule::enum(ItemScope::class)],
            'scope_key' => ['required', 'string', 'max:80'],
        ];
    }

    public function scope(): ItemScope
    {
        return ItemScope::from($this->string('scope')->toString());
    }
}
