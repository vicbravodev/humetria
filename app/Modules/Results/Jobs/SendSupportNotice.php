<?php

declare(strict_types=1);

namespace App\Modules\Results\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Notifications\SupportWaiting;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a person who was channeled through their portal (board V4-N035-Canalizacion) that a message from their company waits
 * there, with the address of their support page (no credential: they enter first). Only ids travel in the job, and the email says nothing of what the message is
 * about. Nothing is sent when the record is no longer a portal notice, the person has no email or left, or the company may not
 * be used.
 */
final class SendSupportNotice implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $tenantId, public readonly string $referralId) {}

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links): void
    {
        $company = $companies->enter($this->tenantId);
        $referral = ScreeningReferral::query()->find($this->referralId, ['id', 'employee_id', 'told']);
        $person = $referral === null ? null : $roster->find($referral->employee_id);

        if ($company->stopped('nom035') || $referral?->told !== HowTold::Portal || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new SupportWaiting($links->to('portal.support.show'), $person->name, $company));
    }
}
