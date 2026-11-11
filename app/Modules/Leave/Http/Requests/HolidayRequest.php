<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A holiday of the company besides those of the law: its day and its name. Owner and hr only. SaveHoliday answers the rest:
 * a day that is already one, a day of rest of the law.
 */
final class HolidayRequest extends FormRequest
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
        return ['date' => ['required', 'date_format:Y-m-d'], 'name' => ['required', 'string', 'max:80']];
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('date')->toString());
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
