<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * A new enrollment link for an active person of the company: good once and for 48 hours, and it revokes the one before.
 * Emailed to the person when they have an email; the URL is returned either way, so RH can hand it over. The token exists
 * in clear only in the URL: only its hash is kept, and it never reaches the log, the audit trail or the queue.
 */
final readonly class IssueEnrollment
{
    private const int HOURS = 48;

    public function __construct(private Roster $roster, private Companies $companies, private RecordAudit $audit) {}

    /** @throws EmployeeNotAvailable when the person left or is not the company's */
    public function handle(string $employeeId, string $userId): string
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            throw new EmployeeNotAvailable;
        }

        $token = Str::random(48);
        $now = CarbonImmutable::now();

        $link = DB::transaction(function () use ($employeeId, $userId, $token, $now): EnrollmentLink {
            EnrollmentLink::query()->where('employee_id', $employeeId)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);

            $link = EnrollmentLink::query()->create([
                'tenant_id' => $this->companies->current()->id,
                'employee_id' => $employeeId,
                'token_hash' => EnrollmentLink::hashToken($token),
                'issued_by' => $userId,
                'expires_at' => $now->addHours(self::HOURS),
            ]);
            $this->audit->handle('attendance.enrollment_issued', $link, ['employee_id' => $employeeId]);

            return $link;
        });

        Log::info('attendance.enrollment_issued', ['link_id' => $link->id, 'employee_id' => $employeeId]);

        $company = $this->companies->current();
        $url = url("a/{$company->slug}/enroll/{$token}");

        if ($person->email === null) {
            Log::info('attendance.enrollment_not_emailed', ['link_id' => $link->id, 'employee_id' => $employeeId]);
        } else {
            Notification::route('mail', $person->email)->notify(new EnrollmentInvitation($url, $person->name, $company));
        }

        return $url;
    }
}
