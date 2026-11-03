<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Paper acknowledgments recorded in bulk: who (the ones ticked, or every pending person a search found), the day they signed
 * and a note about the sheet. Whether the day fits the version is the action's to judge.
 */
final class ManualAcknowledgmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-disclosure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'all_matching' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'employee_ids' => [Rule::requiredIf(! $this->allMatching()), 'nullable', 'array', 'max:1000'],
            'employee_ids.*' => ['uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** RH asked for every pending person the search found, not for the ones ticked. */
    public function allMatching(): bool
    {
        return $this->boolean('all_matching');
    }

    public function search(): string
    {
        return $this->string('q')->trim()->toString();
    }

    /**
     * @return list<string>
     */
    public function employeeIds(): array
    {
        return array_values(array_unique(array_filter(Arr::wrap($this->input('employee_ids')), is_string(...))));
    }

    public function day(): string
    {
        return $this->string('date')->toString();
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }

    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }
}
