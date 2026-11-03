<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use App\Modules\Organization\Models\JobGrade;
use Illuminate\Pagination\LengthAwarePaginator;

final class CompanyJobGrades implements JobGrades
{
    public function page(string $search, int $perPage): LengthAwarePaginator
    {
        return JobGrade::query()
            ->matching(['category', 'level'], $search)
            ->orderBy('category')
            ->orderBy('level')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'category', 'level'])
            ->through(self::data(...));
    }

    public function find(string $id): ?JobGradeData
    {
        $grade = JobGrade::query()->find($id, ['id', 'category', 'level']);

        return $grade === null ? null : self::data($grade);
    }

    public function findMany(array $ids): array
    {
        $grades = [];

        foreach (JobGrade::query()->whereIn('id', $ids)->get(['id', 'category', 'level']) as $grade) {
            $grades[$grade->id] = self::data($grade);
        }

        return $grades;
    }

    private static function data(JobGrade $grade): JobGradeData
    {
        return new JobGradeData($grade->id, $grade->category, $grade->level);
    }
}
