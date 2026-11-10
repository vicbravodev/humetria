<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Notifications\VacationsPortalLink;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails people of the current company the link of «Mis vacaciones» in their own portal: the portal has no menu between
 * tools, so this is how a person gets there the first time. Whoever has no email, left, or is of another company gets
 * none. Links are made here and never logged.
 */
final readonly class SendPortalLinks
{
    public function __construct(private Roster $roster, private PortalAccess $portal, private Companies $companies) {}

    /**
     * @param  list<string>  $employeeIds
     * @param  int  $userId  who of RH asked for it
     * @return int how many emails went out
     */
    public function handle(array $employeeIds, int $userId): int
    {
        $sent = 0;

        // ponytail: sent one by one in the request; a queued job per batch if RH sends to hundreds at once.
        foreach ($this->roster->findMany($employeeIds) as $person) {
            if ($person->active && $person->email !== null) {
                Notification::route('mail', $person->email)->notify(new VacationsPortalLink(route('portal.vacations.show', $this->portal->token($person->id)), $this->companies->current(), $person->name));
                $sent++;
            }
        }

        Log::info('leave.portal_links_sent', ['sent' => $sent, 'asked' => count($employeeIds), 'user_id' => $userId]);

        return $sent;
    }
}
