<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Exceptions\RequestRefused;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * What a person asks for from their portal: the first and the last day, the leave type when it is not vacations, and an
 * optional note. Whose request it is comes from the link, never from what is sent.
 */
final class PortalLeaveRequest extends FormRequest
{
    /** Why the request cannot be made, as "usted": the field it is said on and the words. */
    private const array REFUSALS = [
        'in_the_past' => ['starts_on', 'La fecha de inicio ya pasó. Elija una fecha de hoy en adelante.'],
        'overlaps' => ['starts_on', 'Ya tiene una solicitud en esas fechas. Cancélela o elija otras fechas.'],
        'no_working_days' => ['ends_on', 'Esas fechas no incluyen ningún día laborable.'],
        'too_long' => ['ends_on', 'Una solicitud no puede durar más de un año. Divídala en varias.'],
        'type_unavailable' => ['leave_type_id', 'Ese tipo de permiso ya no está disponible. Elija otro.'],
    ];

    /** The portal link is the credential: the controller opens it. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'leave_type_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:500'],
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

    /**
     * A refusal as the error of its field; one with no words of its own goes to the first day.
     */
    public static function refusal(RequestRefused $refused): ValidationException
    {
        [$field, $message] = self::REFUSALS[$refused->reason] ?? ['starts_on', 'No fue posible registrar su solicitud. Avise a Recursos Humanos.'];

        return ValidationException::withMessages([$field => $message]);
    }
}
