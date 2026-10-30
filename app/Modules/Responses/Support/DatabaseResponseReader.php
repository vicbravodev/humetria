<?php

declare(strict_types=1);

namespace App\Modules\Responses\Support;

use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Data\AnonymousRecord;
use App\Modules\Responses\Data\ScreeningRecord;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use Illuminate\Pagination\LengthAwarePaginator;

final class DatabaseResponseReader implements ResponseReader
{
    public function anonymous(string $campaignId, string $instrument): array
    {
        return array_values(AnonymousResponse::query()
            ->where('campaign_id', $campaignId)
            ->where('instrument', $instrument)
            ->get(['area_id', 'instrument_version', 'answers'])
            ->map(fn (AnonymousResponse $response): AnonymousRecord => new AnonymousRecord($response->area_id, $response->instrument_version, $response->answers))
            ->all());
    }

    public function screeningCounts(string $campaignId): array
    {
        $counts = TraumaScreening::query()
            ->where('campaign_id', $campaignId)
            ->toBase()
            ->selectRaw('count(*)::int as answered, count(*) filter (where requires_clinical_assessment)::int as requiring')
            ->first();

        return [
            'answered' => is_int($counts?->answered) ? $counts->answered : 0,
            'requiring' => is_int($counts?->requiring) ? $counts->requiring : 0,
        ];
    }

    public function requiringCounts(array $campaignIds): array
    {
        if ($campaignIds === []) {
            return [];
        }

        $counts = [];
        $rows = TraumaScreening::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('requires_clinical_assessment', true)
            ->groupBy('campaign_id')
            ->toBase()
            ->selectRaw('campaign_id, count(*)::int as requiring')
            ->get();

        foreach ($rows as $row) {
            if (is_string($row->campaign_id) && is_int($row->requiring)) {
                $counts[$row->campaign_id] = $row->requiring;
            }
        }

        return $counts;
    }

    public function requiringAssessment(string $campaignId, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return TraumaScreening::query()
            ->where('campaign_id', $campaignId)
            ->where('requires_clinical_assessment', true)
            ->orderBy('answered_at')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'employee_id', 'answered_at'], $pageName)
            ->withQueryString()
            ->through(fn (TraumaScreening $screening): ScreeningRecord => new ScreeningRecord($screening->employee_id, $screening->answered_at));
    }
}
