<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The enrollment link of a person's phone, issued to the person themselves when they hold their own portal link (which RH
 * never receives, as it never receives the emailed enrollment link): the same link of one use and 48 hours that
 * `IssueEnrollment` emails, and it replaces the one before. Nobody but the caller reads it: it is returned to be opened at
 * once, kept only as a hash, and never reaches the audit trail, the log or the queue.
 */
final readonly class IssueSelfEnrollment
{
    public function __construct(private Roster $roster, private Companies $companies, private RecordAudit $audit) {}

    /**
     * The path to open, with no host: the person opens it in the same browser, so it can only land on this app.
     *
     * @throws EmployeeNotAvailable when the person left or is not the company's
     */
    public function handle(string $employeeId): string
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            throw new EmployeeNotAvailable;
        }

        $company = $this->companies->current();
        $token = Str::random(48);

        $link = DB::transaction(function () use ($company, $employeeId, $token): EnrollmentLink {
            $link = EnrollmentLink::issue($company->id, $employeeId, $token, null, CarbonImmutable::now());
            $this->audit->handle('attendance.enrollment_issued', $link, ['employee_id' => $employeeId, 'self_service' => true]);

            return $link;
        });

        Log::info('attendance.enrollment_issued', ['link_id' => $link->id, 'employee_id' => $employeeId, 'self_service' => true]);

        return route('attendance.device.enroll.show', [$company->slug, $token], absolute: false);
    }
}
