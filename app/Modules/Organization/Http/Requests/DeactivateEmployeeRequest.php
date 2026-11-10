<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ManagerRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Deactivating an employee. Someone with people who report to them says first whom those will report to.
 */
final class DeactivateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-organization');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['reassign_to' => ['nullable', 'string']];
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
