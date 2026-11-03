<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Notifications\HandlersChanged;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Support\Facades\Notification;

/** Tells the other active handlers that the list of who handles the complaints changed. */
final readonly class HandlerNotices
{
    public function __construct(private ActiveHandlers $active, private Members $members, private Companies $companies) {}

    /** The person it is about does not need the note: only the others get it. */
    public function send(int $aboutUserId, bool $designated): void
    {
        $person = $this->members->find($aboutUserId)->name ?? 'Una persona';
        $company = $this->companies->current();
        $emails = [];

        foreach ($this->active->all() as ['member' => $member]) {
            if ($member->userId !== $aboutUserId) {
                $emails[$member->email] = true;
            }
        }

        foreach (array_keys($emails) as $email) {
            Notification::route('mail', $email)->notify(new HandlersChanged($company->tradeName, $person, $designated, route('tenant.complaints.handlers.index', $company->slug)));
        }
    }
}
