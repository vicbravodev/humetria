<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Billing\Contracts\WhatsAppQuota;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leaves the survey links of some invitations to the queue: those with an email wait in a new batch of jobs, one per
 * block of people, dispatched once the surrounding transaction commits; those without one, and no link from before,
 * are marked so. The mail of those with an email is held from the company's monthly quota first (all or none, like the
 * campaigns'). The request that does it sends nothing.
 */
final readonly class QueueInvitations
{
    public const int BLOCK = 50;

    public function __construct(private Roster $roster, private MailQuota $mail, private WhatsAppQuota $whatsApp) {}

    /**
     * @param  list<string>  $invitationIds
     * @return int how many links were queued.
     */
    public function handle(Survey $survey, array $invitationIds): int
    {
        $invitations = Invitation::query()->among('id', $invitationIds)->get(['id', 'employee_id', 'token_hash', 'invited_at']);
        $people = $this->roster->findMany(array_values($invitations->map(fn (Invitation $invitation): string => $invitation->employee_id)->all()));
        // The channel of each person; whoever already has a link is being reminded, and a reminder never goes through WhatsApp.
        $channels = $invitations->mapWithKeys(fn (Invitation $invitation): array => [$invitation->id => isset($people[$invitation->employee_id]) ? SurveyInvitation::channel($people[$invitation->employee_id], $invitation->invited_at !== null) : null]);
        $reached = $invitations->filter(fn (Invitation $invitation): bool => $channels[$invitation->id] !== null);

        // Held before anything is marked or queued: if they do not all fit, nothing goes out and the person is told how many are missing.
        $this->mail->reserve($channels->filter(fn (?string $channel): bool => $channel === 'mail')->count());
        $this->whatsApp->reserve($channels->filter(fn (?string $channel): bool => $channel === 'whatsapp')->count());

        // Who already holds a link keeps it: it works again as soon as they are reached.
        $unreached = $invitations->diffKeys($reached)->filter(fn (Invitation $invitation): bool => $invitation->token_hash === null);

        if ($unreached->isNotEmpty()) {
            Invitation::query()->among('id', array_values($unreached->map(fn (Invitation $invitation): string => $invitation->id)->all()))->update(['invitation_status' => InvitationStatus::Unreachable, 'invitation_batch' => null]);
        }

        if ($reached->isEmpty()) {
            return 0;
        }

        $batch = (string) Str::uuid();
        $ids = array_values($reached->map(fn (Invitation $invitation): string => $invitation->id)->all());
        Invitation::query()->among('id', $ids)->update(['invitation_status' => InvitationStatus::Queued, 'invitation_batch' => $batch]);
        $jobs = array_map(fn (array $block): SendInvitations => new SendInvitations($survey->id, $batch, $block), array_chunk($ids, self::BLOCK));

        DB::afterCommit(fn () => Bus::batch($jobs)->name("engagement-invitations:{$survey->id}")->allowFailures()->dispatch());

        return count($ids);
    }
}
