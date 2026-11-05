<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Notifications\HandlersChanged;
use App\Modules\Complaints\Notifications\YouWereDesignated;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Support\Facades\Notification;

/** Tells the other active handlers that the list of who handles the complaints changed, and a new handler that they are one. */
final readonly class HandlerNotices
{
    public function __construct(private ActiveHandlers $active, private Members $members, private Companies $companies, private WorkCenters $workCenters) {}

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
            Notification::route('mail', $email)->notify(new HandlersChanged($company, $person, $designated, route('tenant.complaints.handlers.index', $company->slug)));
        }
    }

    /** The person designated learns it from the company, with the scope and the way to their cases (board V4-Correos-2). */
    public function welcome(int $userId, ?string $workCenterId): void
    {
        $person = $this->members->find($userId);

        if ($person === null) {
            return;
        }

        $company = $this->companies->current();
        $center = $workCenterId === null ? null : $this->workCenters->profile($workCenterId)?->name;
        $scope = $center === null ? 'de toda la empresa' : "del centro {$center}";

        Notification::route('mail', $person->email)->notify(new YouWereDesignated($company, $person->name, $scope, route('tenant.complaints.index', $company->slug)));
    }
}
