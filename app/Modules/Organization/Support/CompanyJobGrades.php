<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Actions\FindOrCreateJobGrade;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Data\JobGradeData;
use App\Modules\Organization\Models\JobGrade;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

final readonly class CompanyJobGrades implements JobGrades
{
    public function __construct(private FindOrCreateJobGrade $findOrCreate) {}

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

    public function findOrCreate(string $category, string $level): JobGradeData
    {
        $grade = $this->findOrCreate->handle($category, $level);

        // FindOrCreateJobGrade answers null only when either is missing, and the type says neither is.
        return $grade === null ? throw new InvalidArgumentException('A job grade needs a category and a level.') : self::data($grade);
    }

    private static function data(JobGrade $grade): JobGradeData
    {
        return new JobGradeData($grade->id, $grade->category, $grade->level);
    }
}
