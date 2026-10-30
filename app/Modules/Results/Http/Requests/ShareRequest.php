<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Requests;

use App\Modules\Results\Enums\ShareDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * How long a new link to a report lasts: one of the durations offered, 48 hours at most.
 */
final class ShareRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['duration' => ['required', Rule::enum(ShareDuration::class)]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Elige cuánto dura el enlace: de 15 minutos a 48 horas.';

        return ['duration.required' => $message, 'duration.enum' => $message];
    }

    public function duration(): ShareDuration
    {
        return ShareDuration::from($this->string('duration')->toString());
    }
}
