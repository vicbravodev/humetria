<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Support\ShareMoments;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListReportShares
{
    public const int PER_PAGE = 5;

    /**
     * The links to the report of a scenario that still open, the newest first.
     *
     * @return LengthAwarePaginator<int, array<string, string>>
     */
    public function handle(string $scenarioId): LengthAwarePaginator
    {
        return ReportShare::query()->open()->where('scenario_id', $scenarioId)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'shares')->withQueryString()
            ->through(fn (ReportShare $share): array => $this->row($share));
    }

    /**
     * @return array<string, string>
     */
    private function row(ReportShare $share): array
    {
        return [
            'id' => $share->id,
            'createdAt' => ShareMoments::text($share->created_at),
            'expiresAt' => ShareMoments::text($share->expires_at),
        ];
    }
}
