<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Models\ScreeningReferral;
use Carbon\CarbonImmutable;

/**
 * What the list of people who require a clinical assessment shows of their channeling: each person's record and the
 * count by status, in one query each.
 */
final class ScreeningReferrals
{
    /**
     * The record of each of the given people who has one, as the page shows it.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{status: string, statusLabel: string, referredOn: string, referredOnLabel: string, institution: string}> by employee id
     */
    public function of(string $campaignId, array $employeeIds): array
    {
        $records = [];

        foreach (ScreeningReferral::query()->where('campaign_id', $campaignId)->whereIn('employee_id', $employeeIds)->get() as $referral) {
            $records[$referral->employee_id] = [
                'status' => $referral->status->value,
                'statusLabel' => $referral->status->label(),
                'referredOn' => $referral->referred_on,
                // A day, not a moment: it is read in the display time zone it was written in.
                'referredOnLabel' => ResultsClock::day(CarbonImmutable::parse($referral->referred_on, config()->string('app.display_timezone'))),
                'institution' => $referral->institution,
            ];
        }

        return $records;
    }

    /**
     * How many of the people who require a clinical assessment are pending, referred and attended.
     *
     * @return array{pending: int, referred: int, attended: int}
     */
    public function counts(string $campaignId, int $requiring): array
    {
        $row = ScreeningReferral::query()->where('campaign_id', $campaignId)->toBase()
            ->selectRaw('count(*) filter (where status = ?)::int as referred, count(*) filter (where status = ?)::int as attended', [ReferralStatus::Referred->value, ReferralStatus::Attended->value])
            ->first();
        $referred = is_int($row?->referred) ? $row->referred : 0;
        $attended = is_int($row?->attended) ? $row->attended : 0;

        return ['pending' => $requiring - $referred - $attended, 'referred' => $referred, 'attended' => $attended];
    }
}
