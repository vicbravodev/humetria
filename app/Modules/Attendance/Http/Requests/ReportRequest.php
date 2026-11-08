<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The report for the authority: one work center in use (whether it is one is the controller's to say, a 404) and a period
 * of local dates, both included, of at most 366 days. The page shows a table for up to 93 of them (on screen the figures
 * are computed on every visit); for a longer one it offers only the CSV. Both numbers: config/attendance.php. Owner and hr only: it is evidence handed to an authority.
 */
final class ReportRequest extends FormRequest
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
        // The page opens with no center and the last week that ended; the download needs all three.
        $export = $this->exporting();

        return [
            'center' => [$export ? 'required' : 'nullable', 'string'],
            'from' => [$export ? 'required' : 'required_with:to', 'date_format:Y-m-d'],
            'to' => [$export ? 'required' : 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** Whether the period was asked for: without it the page shows the last week that ended. */
    public function hasPeriod(): bool
    {
        return $this->filled('from');
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $max = config()->integer('attendance.report.export_max_days');

            if ($validator->errors()->isEmpty() && $this->hasPeriod() && $this->days() > $max) {
                $validator->errors()->add('to', "El periodo puede ser de hasta {$max} días.");
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

    /**
     * A wrong period on the page goes back to the center's report (the last week that ended), never to the same address; a
     * wrong download goes back to the page it came from. Either shows the reason under the fields.
     */
    protected function getRedirectUrl(): string
    {
        if ($this->exporting()) {
            return parent::getRedirectUrl();
        }

        $center = $this->string('center')->toString();

        return route('tenant.attendance.report', [$this->tenantSlug(), ...(Str::isUuid($center) ? ['center' => $center] : [])]);
    }

    /** The days of the period asked for, both ends included. */
    public function days(): int
    {
        return (int) round($this->from()->diffInDays($this->to())) + 1;
    }

    /** Whether the page should offer only the CSV: a period too long to show on screen. */
    public function onlyDownload(): bool
    {
        return $this->hasPeriod() && $this->days() > config()->integer('attendance.report.page_max_days');
    }

    private function exporting(): bool
    {
        return $this->routeIs('tenant.attendance.report.export');
    }

    /** The company's address: /admin/{slug}/attendance/report. */
    public function tenantSlug(): string
    {
        return $this->segment(2) ?? '';
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
