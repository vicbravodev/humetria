<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Scenario;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListScenarios
{
    public const int PER_PAGE = 10;

    /**
     * A page of the company's scenarios, the newest first: the active ones or the archived ones.
     *
     * @return LengthAwarePaginator<int, Scenario>
     */
    public function handle(bool $archived): LengthAwarePaginator
    {
        return Scenario::query()
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** @return array{active: int, archived: int} */
    public function counts(): array
    {
        $archived = Scenario::query()->whereNotNull('archived_at')->count();

        return ['active' => Scenario::query()->count() - $archived, 'archived' => $archived];
    }
}
