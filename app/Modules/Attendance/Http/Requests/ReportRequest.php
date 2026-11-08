<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The report for the authority: one work center in use (whether it is one is the controller's to say, a 404) and a period
 * of local dates, both included, of at most 366 days. Owner and hr only: it is evidence handed to an authority.
 */
final class ReportRequest extends FormRequest
{
    public const int MAX_DAYS = 366;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'center' => ['required', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->from()->addDays(self::MAX_DAYS)->lessThanOrEqualTo($this->to())) {
                $validator->errors()->add('to', 'El periodo puede ser de hasta '.self::MAX_DAYS.' días.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'center.required' => 'Elige un centro de trabajo.',
            'center.uuid' => 'Elige un centro de trabajo.',
            'from.date_format' => 'Escribe una fecha inicial válida.',
            'to.date_format' => 'Escribe una fecha final válida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }

    /**
     * `to` is «paso» everywhere else (lang/es/validation.php): here it is the period's last day.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['to' => 'fecha final'];
    }

    public function centerId(): string
    {
        return $this->string('center')->toString();
    }

    /** The period's first local date. */
    public function from(): CarbonImmutable
    {
        return $this->localDate('from');
    }

    /** Its last local date. */
    public function to(): CarbonImmutable
    {
        return $this->localDate('to');
    }

    private function localDate(string $key): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->string($key)->toString(), config()->string('app.display_timezone'));

        // The rules let only a Y-m-d date through.
        return $date instanceof CarbonImmutable ? $date : throw new LogicException("The {$key} date was not validated.");
    }
}
