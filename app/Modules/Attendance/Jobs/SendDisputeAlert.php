<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Jobs;

use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Notifications\WeekDisputedAlert;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Emails RH (the company's contact) that a person disputed their week: who, where and which week, never the reason (it is
 * read in Humetría). Only ids travel in the job. A company without Asistencia or blocked, one without a contact, or a week
 * no longer disputed when it runs gets nothing.
 */
final class SendDisputeAlert implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300]; // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveArrayItem

    public function __construct(public readonly string $tenantId, public readonly string $confirmationId) {}

    public function handle(Companies $companies, Roster $roster, WorkCenters $workCenters): void
    {
        $company = $companies->enter($this->tenantId);
        $contact = $company->stopped('attendance') ? null : $companies->contact();
        $week = WeeklyConfirmation::query()->find($this->confirmationId, ['id', 'employee_id', 'week_start', 'status']);

        if ($contact === null || $week?->status !== WeekStatus::Disputed) {
            return;
        }

        $person = $roster->find($week->employee_id);
        $center = $person === null ? null : $workCenters->findActive($person->workCenterId);

        Notification::route('mail', $contact)->notify(new WeekDisputedAlert(
            $company,
            $person === null ? 'Una persona' : $person->name,
            $center === null ? '' : $center->name,
            $week->week_start,
            route('tenant.attendance.index', [$company->slug, 'status' => 'disputed', 'week' => $week->week_start->toDateString()]),
        ));
    }
}
