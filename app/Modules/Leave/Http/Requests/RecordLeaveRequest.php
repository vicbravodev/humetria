<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Exceptions\RequestRefused;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * RH records vacations or a leave for somebody (owner and hr only): the person, the first and the last day, the leave type
 * when it is not vacations, an optional note and whether to answer it in the same step. The days may already be gone.
 */
final class RecordLeaveRequest extends FormRequest
{
    /** Why it cannot be recorded, as "tú": the field it is said on and the words. */
    private const array REFUSALS = [
        'inactive_employee' => ['employee_id', 'La persona ya no está activa en la empresa.'],
        'overlaps' => ['starts_on', 'La persona ya tiene una solicitud en esas fechas.'],
        'no_working_days' => ['ends_on', 'Esas fechas no incluyen ningún día laborable.'],
        'too_long' => ['ends_on', 'Una solicitud no puede durar más de un año. Divídela en varias.'],
        'type_unavailable' => ['leave_type_id', 'Ese tipo de permiso ya no está disponible.'],
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'leave_type_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
            'approve' => ['boolean'],
        ];
    }

    public function startsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('starts_on')->toString());
    }

    public function endsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('ends_on')->toString());
    }

    public function typeId(): ?string
    {
        $id = $this->string('leave_type_id')->toString();

        return $id === '' ? null : $id;
    }

    /** The user of RH who records. */
    public function userId(): int
    {
        return $this->user()->id ?? abort(403);
    }

    /**
     * A refusal as the error of its field; one with no words of its own goes to the first day.
     */
    public static function refusal(RequestRefused $refused): ValidationException
    {
        [$field, $message] = self::REFUSALS[$refused->reason] ?? ['starts_on', 'No fue posible registrar la solicitud.'];

        return ValidationException::withMessages([$field => $message]);
    }
}
