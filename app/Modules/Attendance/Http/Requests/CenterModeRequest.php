<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Enums\CenterMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** How the people of a center mark: «Igual que la empresa» or «En campo». */
final class CenterModeRequest extends FormRequest
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
        return ['mode' => ['required', 'string', Rule::enum(CenterMode::class)]];
    }

    public function mode(): CenterMode
    {
        return $this->enum('mode', CenterMode::class) ?? CenterMode::Company;
    }
}
