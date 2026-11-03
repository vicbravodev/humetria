<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

final readonly class FindOrCreateJobGrade
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /** The company's grade with that category and level, whatever the case, or a new one. */
    public function handle(string $category, string $level): JobGrade
    {
        $category = trim($category);
        $level = trim($level);
        $existing = JobGrade::query()->whereRaw('lower(category) = lower(?) and lower(level) = lower(?)', [$category, $level])->first();

        if ($existing !== null) {
            return $existing;
        }

        $grade = JobGrade::query()->create(['tenant_id' => $this->currentTenant->id(), 'category' => $category, 'level' => $level]);
        Log::info('job_grade.created', ['job_grade_id' => $grade->id]);

        return $grade;
    }
}
