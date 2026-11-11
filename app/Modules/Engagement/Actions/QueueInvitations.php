<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Leaves the survey links of some invitations to the queue: those with an email wait in a new batch of jobs, one per
 * block of people, dispatched once the surrounding transaction commits; those without one, and no link from before,
 * are marked so. The request that does it sends nothing.
 */
final readonly class QueueInvitations
{
    public const int BLOCK = 50;

    public function __construct(private Roster $roster) {}

    /**
     * @param  list<string>  $invitationIds
     * @return int how many links were queued.
     */
    public function handle(Survey $survey, array $invitationIds): int
    {
        $invitations = Invitation::query()->among('id', $invitationIds)->get(['id', 'employee_id', 'token_hash']);
        $people = $this->roster->findMany(array_values($invitations->map(fn (Invitation $invitation): string => $invitation->employee_id)->all()));
        $reached = $invitations->filter(fn (Invitation $invitation): bool => ($people[$invitation->employee_id]->email ?? '') !== '');
        // Who already holds a link keeps it: it works again as soon as they are reached.
        $unreached = $invitations->diffKeys($reached)->filter(fn (Invitation $invitation): bool => $invitation->token_hash === null);

        if ($unreached->isNotEmpty()) {
            Invitation::query()->among('id', array_values($unreached->map(fn (Invitation $invitation): string => $invitation->id)->all()))->update(['invitation_status' => InvitationStatus::NoEmail, 'invitation_batch' => null]);
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
