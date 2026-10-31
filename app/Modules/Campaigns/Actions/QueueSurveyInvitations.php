<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leaves the survey links of some people of a campaign to the queue: those with an email wait in a new batch of jobs,
 * one per block of people, dispatched once the surrounding transaction commits; those without one, and no link from
 * before, are marked so. The request that does it sends nothing.
 */
final class QueueSurveyInvitations
{
    public const int BLOCK = 50;

    /**
     * @param  array<string, bool>  $participants  participant id => whether a channel reaches them (SurveyInvitation::routes())
     * @return int how many links were queued.
     */
    public function handle(Campaign $campaign, array $participants): int
    {
        $reached = array_keys(array_filter($participants));
        $unreachable = array_keys(array_filter($participants, fn (bool $hasEmail): bool => ! $hasEmail));

        // Who already holds a link keeps it: it works again as soon as they have a guide pending (someone who returned).
        if ($unreachable !== []) {
            Participant::query()->among('id', $unreachable)->whereNull('token_hash')->update(['invitation_status' => InvitationStatus::NoEmail, 'invitation_batch' => null]);
        }

        if ($reached === []) {
            return 0;
        }

        $batch = (string) Str::uuid();
        Participant::query()->among('id', $reached)->update(['invitation_status' => InvitationStatus::Queued, 'invitation_batch' => $batch]);
        $campaign->update(['invitation_batch' => $batch]);
        $jobs = array_map(fn (array $block): SendSurveyInvitations => new SendSurveyInvitations($campaign->id, $batch, $block), array_chunk($reached, self::BLOCK));

        DB::afterCommit(fn () => Bus::batch($jobs)->name("campaign-invitations:{$campaign->id}")->allowFailures()->dispatch());

        return count($reached);
    }
}
