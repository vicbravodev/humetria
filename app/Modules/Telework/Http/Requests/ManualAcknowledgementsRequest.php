<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

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
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'employee_ids.*' => ['uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
            'note' => ['required', 'string', 'max:500'],
        ];
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
