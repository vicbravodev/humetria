<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The address RH searches to center a zone. */
final class PlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['address' => ['required', 'string', 'min:3', 'max:120']];
    }

    public function address(): string
    {
        return $this->string('address')->trim()->toString();
    }
}
