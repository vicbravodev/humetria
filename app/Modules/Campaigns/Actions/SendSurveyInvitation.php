<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Campaigns\Support\CampaignClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;

/**
 * Gives a participant a new link and emails it once the surrounding transaction commits. A new link replaces the one
 * before it: an older email stops working.
 */
final class SendSurveyInvitation
{
    public function handle(Campaign $campaign, Participant $participant, string $email, string $tradeName): void
    {
        $token = Str::random(64);
        $participant->update(['token_hash' => Participant::hashToken($token), 'invited_at' => now()]);
        $lastDay = CampaignClock::day($campaign->closes_on ?? throw new LogicException('Only a launched campaign invites.'), isDate: true);

        // ponytail: sent within the request, as the other invitations are; queue them once a worker runs, since a few
        // hundred people take seconds.
        DB::afterCommit(fn () => Notification::route('mail', $email)->notify(new SurveyInvitation($tradeName, $token, $lastDay)));
    }
}
