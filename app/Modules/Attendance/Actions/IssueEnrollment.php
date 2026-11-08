<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Data\IssuedEnrollment;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Attendance\Support\MaskedEmail;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * A new enrollment link for an active person of the company: good once and for 48 hours, and it revokes the one before.
 * Emailed to the person, and then RH never gets it: RH may not link a device as them. Only when the person has no email
 * is the URL returned, for RH to hand over, and the audit trail says RH delivered it. The URL is built on the app's
 * address, never the request's host. The token exists in clear only in the URL: only its hash is kept, and it never
 * reaches the log, the audit trail or the queue.
 */
final readonly class IssueEnrollment
{
    private const int HOURS = 48;

    public function __construct(private Roster $roster, private Companies $companies, private RecordAudit $audit) {}

    /** @throws EmployeeNotAvailable when the person left or is not the company's */
    public function handle(string $employeeId, int $userId): IssuedEnrollment
    {
        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            throw new EmployeeNotAvailable;
        }

        $token = Str::random(48);
        $now = CarbonImmutable::now();
        $byRh = $person->email === null;
        // Masked, so a link sent to another address than before shows in the audit trail without the address itself.
        $sentTo = $person->email === null ? null : MaskedEmail::of($person->email);

        $link = DB::transaction(function () use ($employeeId, $userId, $token, $now, $byRh, $sentTo): EnrollmentLink {
            // One person's issues one at a time: two at once would both find nothing to revoke, and the second would hit the
            // one-pending index.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["attendance_enrollment_links:{$employeeId}"]);
            EnrollmentLink::query()->where('employee_id', $employeeId)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => $now]);

            $link = EnrollmentLink::query()->create([
                'tenant_id' => $this->companies->current()->id,
                'employee_id' => $employeeId,
                'token_hash' => EnrollmentLink::hashToken($token),
                'issued_by' => $userId,
                'expires_at' => $now->addHours(self::HOURS),
            ]);
            $this->audit->handle('attendance.enrollment_issued', $link, ['employee_id' => $employeeId, 'delivered_by_rh' => $byRh, 'sent_to' => $sentTo]);

            return $link;
        });

        Log::info('attendance.enrollment_issued', ['link_id' => $link->id, 'employee_id' => $employeeId, 'sent_to' => $sentTo]);

        $company = $this->companies->current();
        $url = rtrim(config()->string('app.url'), '/').route('attendance.device.enroll.show', [$company->slug, $token], absolute: false);

        if ($person->email === null) {
            Log::info('attendance.enrollment_not_emailed', ['link_id' => $link->id, 'employee_id' => $employeeId]);

            return new IssuedEnrollment($link->id, $url);
        }

        Notification::route('mail', $person->email)->notify(new EnrollmentInvitation($url, $person->name, $company));

        return new IssuedEnrollment($link->id, null);
    }
}
