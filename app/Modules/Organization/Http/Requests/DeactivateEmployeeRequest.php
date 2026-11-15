<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Enums\DeactivationReason;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ManagerRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Deactivating an employee: the day it takes effect (today or before, never before they were hired) and why. Someone with people who report to them says first whom those will report to.
 */
final class DeactivateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-organization');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('employee');
        $hiredOn = is_string($id) ? Employee::query()->find($id, ['id', 'hired_on'])?->hired_on : null;

        return [
            'terminated_on' => array_filter([
                'required', 'date_format:Y-m-d', 'before_or_equal:'.self::today()->toDateString(),
                $hiredOn === null ? null : 'after_or_equal:'.$hiredOn->toDateString(),
            ]),
            'reason' => ['required', Rule::enum(DeactivationReason::class)],
            'reassign_to' => ['nullable', 'string'],
        ];
    }

    /** The day the person left, as HR typed it. */
    public function terminatedOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('terminated_on')->toString());
    }

    public function reason(): DeactivationReason
    {
        return DeactivationReason::from($this->string('reason')->toString());
    }

    /** Today, the day of the company (not UTC's). */
    private static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(ReportingLines $lines, ManagerRules $managers): array
    {
        return [function (Validator $validator) use ($lines, $managers): void {
            $id = $this->route('employee');
            $employee = is_string($id) ? Employee::query()->find($id, ['id', 'deactivated_at']) : null;

            // Somebody who is not there, or who already left, is the controller's to answer.
            if ($employee === null || $employee->deactivated_at !== null || $validator->errors()->has('reassign_to')) {
                return;
            }

            $reports = count($lines->directReportIds($employee->id));
            $replacement = $this->reassignTo();

            $message = match (true) {
                $replacement !== null && $replacement === $employee->id => 'Una persona no puede ser su propio jefe.',
                // Whoever reports to the person may take their place: only that they exist and are active is asked.
                $replacement !== null => $managers->violation(null, $replacement),
                $reports === 1 => 'Elige a quién le reportará su reporte directo.',
                $reports > 1 => "Elige a quién le reportarán sus {$reports} reportes directos.",
                default => null,
            };

            if ($message !== null) {
                $validator->errors()->add('reassign_to', $message);
            }
        }];
    }

    /** Whom the direct reports will report to, when one was chosen. */
    public function reassignTo(): ?string
    {
        return $this->filled('reassign_to') ? $this->string('reassign_to')->toString() : null;
    }
}
