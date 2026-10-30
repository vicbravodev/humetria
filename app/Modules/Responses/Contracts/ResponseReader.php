<?php

declare(strict_types=1);

namespace App\Modules\Responses\Contracts;

use App\Modules\Responses\Data\AnonymousRecord;
use App\Modules\Responses\Data\ScreeningRecord;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads the answers of the current company for its results. An anonymous answer comes with its area and nothing else,
 * in no particular order: no id and no position that could tell when it was given (criterio C-22).
 */
interface ResponseReader
{
    /**
     * Every anonymous answer to one guide of a campaign.
     *
     * @return list<AnonymousRecord>
     */
    public function anonymous(string $campaignId, string $instrument): array;

    /**
     * How many Guía I a campaign has, and how many of them require a clinical assessment.
     *
     * @return array{answered: int, requiring: int}
     */
    public function screeningCounts(string $campaignId): array;

    /**
     * Who requires a clinical assessment in a campaign (num. 5.5), by when they answered, a page at a time.
     *
     * @return LengthAwarePaginator<int, ScreeningRecord>
     */
    public function requiringAssessment(string $campaignId, int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator;
}
