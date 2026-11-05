<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Benefit;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListBenefits
{
    public const int PER_PAGE = 15;

    /**
     * A page of the catalog: the active benefits or the archived ones, with what was typed in the name (ignoring case and
     * accents), the base ones first and each group by name, with its terms from the latest vigencia to the oldest.
     *
     * @return LengthAwarePaginator<int, Benefit>
     */
    public function handle(string $search, bool $archived): LengthAwarePaginator
    {
        return Benefit::query()
            ->with(['terms' => fn ($terms) => $terms->orderByDesc('effective_from')])
            ->matching(['name'], $search)
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->orderByRaw('base_key is null')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
