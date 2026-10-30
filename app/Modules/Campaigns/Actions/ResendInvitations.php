<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sends a new link to everyone with a guide pending and an email (or to some people): each new link replaces the one
 * before it, so a lost or forwarded email stops working.
 */
final readonly class ResendInvitations
{
    public function __construct(private Roster $roster, private Companies $companies, private SendSurveyInvitation $sendInvitation) {}

    /**
     * @param  list<string>|null  $employeeIds  only these people, when given.
     * @return int how many people got a new link.
     */
    public function handle(Campaign $campaign, ?array $employeeIds = null): int
    {
        $participants = $campaign->participants()
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds ?? []))
            ->whereHas('instruments', fn ($query) => $query->where('status', InstrumentStatus::Pending))
            ->get();
        $people = $this->roster->findMany(array_values($participants->map(fn (Participant $participant): string => $participant->employee_id)->all()));
        $tradeName = $this->companies->current()->tradeName;

        $sent = DB::transaction(function () use ($campaign, $participants, $people, $tradeName): int {
            $sent = 0;

            foreach ($participants as $participant) {
                $email = $people[$participant->employee_id]->email ?? null;

                if ($email !== null) {
                    $this->sendInvitation->handle($campaign, $participant, $email, $tradeName);
                    $sent++;
                }
            }

            return $sent;
        });

        Log::info('campaign.invitations_resent', ['campaign_id' => $campaign->id, 'sent' => $sent]);

        return $sent;
    }
}
