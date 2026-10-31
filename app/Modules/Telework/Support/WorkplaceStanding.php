<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Carbon\CarbonImmutable;

/**
 * Where a workplace stands, derived and never stored (criterios C-12 and C-13; designer decisions 3 and 9). The latest
 * review decides; a validation lapses `periodMonths` after the day it was made, with the period in force today, so
 * changing the period applies to every workplace at once.
 *
 * Dates are calendar dates in the company's display time zone (America/Mexico_City): a review made at 21:00 on the 14th
 * there is of the 14th, though it is the 15th in UTC. A validation made on 2026-01-15 for 12 months is still valid on
 * 2027-01-14 and pending from 2027-01-15, which `expiresOn()` returns.
 */
final readonly class WorkplaceStanding
{
    public function __construct(private Settings $settings) {}

    public function of(Workplace $workplace): WorkplaceStatus
    {
        if ($workplace->retired_at !== null) {
            return WorkplaceStatus::Retired;
        }

        $review = $this->latest($workplace);

        if ($review === null) {
            return WorkplaceStatus::Pending;
        }

        if (! $review->decision->validates()) {
            return WorkplaceStatus::NotValid;
        }

        return $this->lapsed($review) ? WorkplaceStatus::Pending : WorkplaceStatus::from($review->decision->value);
    }

    /** Whether one of the person's workplaces is validated (with or without a plan) and in force today. */
    public function hasOneInForce(Teleworker $teleworker): bool
    {
        return $teleworker->loadMissing('workplaces')->workplaces->contains(
            fn (Workplace $workplace): bool => in_array($this->of($workplace), [WorkplaceStatus::Valid, WorkplaceStatus::ValidWithPlan], true),
        );
    }

    /**
     * The first day a validation no longer holds, at the start of that day in the display time zone; null unless the
     * latest review validated the workplace.
     */
    public function expiresOn(Workplace $workplace): ?CarbonImmutable
    {
        $review = $this->latest($workplace);

        return $review === null || ! $review->decision->validates() ? null : $this->expiry($review);
    }

    /**
     * The latest review of each workplace, in one query per thousand: what a pass over many workplaces needs instead of
     * asking `of()` for each.
     *
     * @param  list<string>  $workplaceIds
     * @return array<string, WorkplaceReview> by workplace id; a workplace never reviewed is missing.
     */
    public function latestReviews(array $workplaceIds): array
    {
        $latest = [];

        foreach (array_chunk($workplaceIds, 1000) as $ids) {
            $reviews = WorkplaceReview::query()->selectRaw('distinct on (workplace_id) *')->whereIn('workplace_id', $ids)
                ->orderBy('workplace_id')->orderByDesc('decided_at')->orderByDesc('id')->get();

            foreach ($reviews as $review) {
                $latest[$review->workplace_id] = $review;
            }
        }

        return $latest;
    }

    private function latest(Workplace $workplace): ?WorkplaceReview
    {
        return WorkplaceReview::query()->where('workplace_id', $workplace->id)->orderByDesc('decided_at')->orderByDesc('id')->first();
    }

    private function lapsed(WorkplaceReview $review): bool
    {
        return $this->expiry($review)->lessThanOrEqualTo(CarbonImmutable::now());
    }

    /** The first day the validation of a review no longer holds (start of that day in the display time zone). */
    public function expiry(WorkplaceReview $review): CarbonImmutable
    {
        return $review->decided_at->setTimezone(config()->string('app.display_timezone'))->startOfDay()->addMonthsNoOverflow($this->settings->periodMonths());
    }
}
