<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Models\TeleworkDocument;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Paper acknowledgements recorded in bulk: who, the day they signed (not in the future) and a note about the sheet.
 */
final class ManualAcknowledgementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $zone = config()->string('app.display_timezone');
        $id = $this->route('document');
        $published = is_string($id) && Str::isUuid($id) ? TeleworkDocument::query()->find($id)?->created_at : null;

        return [
            'all_matching' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'employee_ids' => [Rule::requiredIf(! $this->allMatching()), 'nullable', 'array', 'max:1000'],
            'employee_ids.*' => ['uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now($zone)->toDateString(), ...($published === null ? [] : ['after_or_equal:'.$published->setTimezone($zone)->toDateString()])],
            'note' => ['required', 'string', 'max:500'],
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

    /** @return list<string> */
    public function employeeIds(): array
    {
        return array_values(array_unique(array_filter(Arr::wrap($this->input('employee_ids')), is_string(...))));
    }

    /** The start of the day they signed, in the company's time zone. */
    public function signedOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString(), config()->string('app.display_timezone'))->startOfDay();
    }

    public function note(): string
    {
        return $this->string('note')->trim()->toString();
    }
}
