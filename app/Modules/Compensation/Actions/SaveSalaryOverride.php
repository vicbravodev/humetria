<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Data\OverrideData;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Support\OverrideTargets;
use App\Modules\Compensation\Support\SalaryRules;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class SaveSalaryOverride
{
    public function __construct(private CurrentTenant $currentTenant, private OverrideTargets $targets, private RecordAudit $audit) {}

    /**
     * What a person really earns per day from a date on. The salary of the same date is replaced; one of a later date
     * leaves the earlier ones as history, and the one in force on a day is the latest that started on or before it.
     *
     * @throws ValidationException
     */
    public function handle(OverrideData $data): SalaryOverride
    {
        $number = trim($data->employeeNumber);
        $salaryProblem = SalaryRules::salaryProblem($data->dailySalaryCents);
        $errors = [
            ...($number === '' || mb_strlen($number) > SalaryRules::NUMBER_LENGTH ? ['employee_number' => 'Escribe el ID de la empresa de la persona, de '.SalaryRules::NUMBER_LENGTH.' caracteres a lo más.'] : []),
            ...($salaryProblem === null ? [] : ['daily_salary' => $salaryProblem]),
            ...($data->note !== null && mb_strlen($data->note) > SalaryRules::NOTE_LENGTH ? ['note' => 'La nota puede tener '.SalaryRules::NOTE_LENGTH.' caracteres a lo más.'] : []),
        ];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $key = mb_strtolower($number);
        $target = $this->targets->resolve([$number]);

        if (isset($target['problems'][$key])) {
            throw ValidationException::withMessages(['employee_number' => $target['problems'][$key]]);
        }

        return DB::transaction(function () use ($data, $target, $key): SalaryOverride {
            $override = SalaryOverride::query()->updateOrCreate(
                ['employee_number' => $target['found'][$key], 'effective_from' => $data->effectiveFrom->toDateString()],
                ['tenant_id' => $this->currentTenant->id(), 'daily_salary_cents' => $data->dailySalaryCents, 'note' => $data->note],
            );

            // Ids and counts only: a salary is money, and who earns it is not for a log or the audit trail.
            Log::info('compensation.override_saved', ['override_id' => $override->id]);
            $this->audit->handle('compensation.override_saved', null, ['overrides' => 1]);

            return $override;
        });
    }
}
