<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Data\GradeSalaryData;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Support\SalaryRules;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class SaveGradeSalary
{
    public function __construct(private CurrentTenant $currentTenant, private JobGrades $jobGrades) {}

    /**
     * The salary of a category and level from a date on, creating the category and level when the company has none like
     * it; the salary of the same date is replaced, so a grade has one per date.
     *
     * @throws ValidationException
     */
    public function handle(GradeSalaryData $data): GradeSalary
    {
        $salaryProblem = SalaryRules::salaryProblem($data->dailySalaryCents);
        $errors = [...SalaryRules::gradeProblems($data->category, $data->level), ...($salaryProblem === null ? [] : ['daily_salary' => $salaryProblem])];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($data): GradeSalary {
            $grade = $this->jobGrades->findOrCreate($data->category, $data->level);
            $salary = GradeSalary::query()->updateOrCreate(
                ['job_grade_id' => $grade->id, 'effective_from' => $data->effectiveFrom->toDateString()],
                ['tenant_id' => $this->currentTenant->id(), 'daily_salary_cents' => $data->dailySalaryCents],
            );

            // Ids only: a salary is money, which stays out of logs.
            Log::info('compensation.grade_salary_saved', ['job_grade_id' => $grade->id, 'grade_salary_id' => $salary->id]);

            return $salary;
        });
    }
}
