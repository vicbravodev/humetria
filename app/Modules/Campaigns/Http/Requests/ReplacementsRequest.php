<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * How many people to add to the sample, and of which gender: the gender of who will not answer, so the sample keeps
 * the split of III.1 (criterio C-10).
 */
final class ReplacementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-campaigns');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:1000'],
            'gender' => ['required', Rule::in(['female', 'male', 'other'])],
        ];
    }

    public function count(): int
    {
        return $this->integer('count');
    }

    public function gender(): string
    {
        return $this->string('gender')->toString();
    }
}
