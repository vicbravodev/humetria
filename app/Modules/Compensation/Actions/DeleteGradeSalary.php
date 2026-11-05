<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Models\GradeSalary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DeleteGradeSalary
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * Takes out a salary of the tabulator that was typed by mistake (a wrong date or amount): the one in force on a day is
     * again the latest that started before it. Scenarios are costed live, so none holds a copy.
     */
    public function handle(GradeSalary $salary): void
    {
        DB::transaction(function () use ($salary): void {
            $salary->delete();
            // Ids and a count only: a salary is money.
            $this->audit->handle('compensation.grade_salary_deleted', null, ['grade_salaries' => 1]);
        });

        Log::info('compensation.grade_salary_deleted', ['job_grade_id' => $salary->job_grade_id, 'grade_salary_id' => $salary->id]);
    }
}
