<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Contracts\PortalLinks;
use App\Modules\Telework\Notifications\PortalLinkEmail;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a person their portal link by email as the notification is built, never queued: the token must not reach the queue.
 */
final readonly class EmailedPortalLinks implements PortalLinks
{
    public function __construct(private Roster $roster, private IssuePortalLink $issue, private Companies $companies) {}

    public function sendTo(string $employeeId): bool
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active || $person->email === null) {
            return false;
        }

        Notification::route('mail', $person->email)->notify(new PortalLinkEmail($this->issue->handle($person->id), $person->name, $this->companies->current()));
        Log::info('portal.link_sent', ['employee_id' => $person->id]);

        return true;
    }
}
