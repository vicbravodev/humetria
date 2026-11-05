<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\JobGrade;
use Illuminate\Http\Request;

/**
 * The company's categories or levels that have what was typed, for a field that searches them: a few at a time,
 * without repeats, by name. They are no secret inside the company: any member may read them.
 */
final class JobGradeOptionsController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request, mixed $tenant, string $field): array
    {
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $query = JobGrade::query();
        $grades = $field === 'level'
            ? $query->matching(['level'], $search)->select('level')->distinct()->orderBy('level')->limit(self::LIMIT)->get()
            : $query->matching(['category'], $search)->select('category')->distinct()->orderBy('category')->limit(self::LIMIT)->get();
        $options = [];

        foreach ($grades as $grade) {
            $name = $field === 'level' ? $grade->level : $grade->category;
            $options[] = ['value' => $name, 'label' => $name];
        }

        return $options;
    }
}
