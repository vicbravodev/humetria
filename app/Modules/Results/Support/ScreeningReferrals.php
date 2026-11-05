<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Models\ScreeningReferral;
use Carbon\CarbonImmutable;

/**
 * What the list of people who require a clinical assessment shows of their channeling: each person's record, who has one
 * and the count by status, in one query each.
 */
final class ScreeningReferrals
{
    /**
     * The record of each of the given people who has one, as the page shows it: where they were channeled and how they were
     * told, the day, the note and when to ask how they are. Days are written in the display time zone they were written in.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{status: string, statusLabel: string, destination: string, destinationLabel: string, told: string, toldLabel: string, referredOn: string, referredOnLabel: string, note: string|null, remindOn: string|null, institution: string|null}> by employee id
     */
    public function of(string $campaignId, array $employeeIds): array
    {
        $records = [];

        foreach (ScreeningReferral::query()->where('campaign_id', $campaignId)->whereIn('employee_id', $employeeIds)->get() as $referral) {
            $records[$referral->employee_id] = [
                'status' => $referral->status->value,
                'statusLabel' => $referral->status->label(),
                'destination' => $referral->destination->value,
                'destinationLabel' => $referral->destination->label(),
                'told' => $referral->told->value,
                'toldLabel' => $referral->told->label(),
                'referredOn' => $referral->referred_on,
                'referredOnLabel' => self::day($referral->referred_on),
                'note' => $referral->note,
                'remindOn' => $referral->remind_on === null ? null : self::day($referral->remind_on),
                'institution' => $referral->institution,
            ];
        }

        return $records;
    }

    /**
     * Who of a campaign already has a record: the people a view of the channeled lists, and the ones the view of who waits
     * leaves out. At most everyone who requires an assessment in it.
     *
     * @return list<string> employee ids
     */
    public function channeled(string $campaignId): array
    {
        return array_values(ScreeningReferral::query()->where('campaign_id', $campaignId)->get(['employee_id'])->map(fn (ScreeningReferral $referral): string => $referral->employee_id)->all());
    }

    /** A day, not a moment: it is read in the display time zone it was written in. */
    private static function day(string $date): string
    {
        return ResultsClock::day(CarbonImmutable::parse($date, config()->string('app.display_timezone')));
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
