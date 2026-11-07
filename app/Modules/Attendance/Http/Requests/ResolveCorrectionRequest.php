<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\ResolveCorrection;
use Illuminate\Foundation\Http\FormRequest;

/** RH approves a correction request, with a note if they want, or rejects it with one: the person reads it. */
final class ResolveCorrectionRequest extends FormRequest
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
        $rejecting = $this->route()?->getName() === 'tenant.attendance.corrections.reject';

        return [
            'note' => [$rejecting ? 'required' : 'nullable', 'string', 'min:'.ResolveCorrection::MIN_NOTE, 'max:500'],
        ];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->toString() : null;
    }
}
