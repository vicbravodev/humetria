<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(IssueEnrollment::class, EnrollmentLink::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->rh = User::factory()->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.test']);
    Notification::fake();
});

/** The token at the end of an enrollment URL. */
function enrollmentToken(string $url): string
{
    return basename($url);
}

it('issues a link of one use for 48 hours, keeping only its hash, and emails it to the person', function (): void {
    $url = resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id);
    $token = enrollmentToken($url);

    $link = EnrollmentLink::query()->sole();
    expect($url)->toBe(url("a/acme/enroll/{$token}"))
        ->and(strlen($token))->toBeGreaterThanOrEqual(40)
        ->and($link)->employee_id->toBe($this->ana->id)->tenant_id->toBe($this->tenant->id)->issued_by->toBe($this->rh->id)->used_at->toBeNull()->revoked_at->toBeNull()
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->expires_at->toIso8601String())->toBe('2026-11-09T15:00:00+00:00')
        ->and(json_encode(DB::table('attendance_enrollment_links')->first(), JSON_THROW_ON_ERROR))->not->toContain($token);

    $sent = null;
    Notification::assertSentOnDemand(EnrollmentInvitation::class, function (EnrollmentInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $mail = $notification->toMail($notifiable);
        $sent = ['to' => $notifiable->routes['mail'], 'channels' => $channels, 'url' => $mail->actionUrl, 'greeting' => $mail->greeting];

        return true;
    });
    expect($sent)->toBe(['to' => 'ana@acme.test', 'channels' => ['mail'], 'url' => $url, 'greeting' => 'Hola, Ana López']);
});

it('writes the issue to the audit trail and the log with ids only, never the token', function (): void {
    Log::spy();

    $token = enrollmentToken(resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id));

    $link = EnrollmentLink::query()->sole();
    $entry = AuditEntry::query()->where('event', 'attendance.enrollment_issued')->sole();
    expect($entry)->subject_id->toBe($link->id)->metadata->toBe(['employee_id' => $this->ana->id])
        ->and(json_encode($entry->toArray(), JSON_THROW_ON_ERROR))->not->toContain($token);
    Log::shouldHaveReceived('info')->once()->with('attendance.enrollment_issued', ['link_id' => $link->id, 'employee_id' => $this->ana->id]);
});

it('still gives the link to RH, to hand it over, when the person has no email, and logs that none was sent', function (): void {
    $pepe = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'email' => null]);
    Log::spy();

    $url = resolve(IssueEnrollment::class)->handle($pepe->id, (string) $this->rh->id);

    $link = EnrollmentLink::query()->sole();
    expect(EnrollmentLink::findPending(enrollmentToken($url)))->id->toBe($link->id);
    Notification::assertNothingSent();
    Log::shouldHaveReceived('info')->with('attendance.enrollment_not_emailed', ['link_id' => $link->id, 'employee_id' => $pepe->id]);
});

it('keeps one pending link per person: a new one revokes the one before', function (): void {
    $first = enrollmentToken(resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id));
    $second = enrollmentToken(resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id));

    expect(EnrollmentLink::findPending($first))->toBeNull()
        ->and(EnrollmentLink::findPending($second))->not->toBeNull()
        ->and(EnrollmentLink::query()->whereNotNull('revoked_at')->count())->toBe(1);
});

it('lets the database hold only one pending link per person', function (): void {
    resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id);
    $link = EnrollmentLink::query()->sole();

    expect(fn () => EnrollmentLink::query()->create([...$link->only(['tenant_id', 'employee_id', 'issued_by', 'expires_at']), 'token_hash' => hash('sha256', 'other')]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('finds a link until the second its 48 hours end', function (): void {
    $token = enrollmentToken(resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id));

    $this->travelTo(CarbonImmutable::parse('2026-11-09 14:59:59', 'UTC'));
    expect(EnrollmentLink::findPending($token))->not->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    expect(EnrollmentLink::findPending($token))->toBeNull();
});

it('does not find a used link, an unknown one, or one of another company', function (): void {
    $token = enrollmentToken(resolve(IssueEnrollment::class)->handle($this->ana->id, (string) $this->rh->id));

    workIn(Tenant::factory()->create());
    expect(EnrollmentLink::findPending($token))->toBeNull();

    workIn($this->tenant);
    expect(EnrollmentLink::findPending('unknown'))->toBeNull()
        ->and(EnrollmentLink::findPending($token))->not->toBeNull();

    EnrollmentLink::query()->update(['used_at' => now()]);
    expect(EnrollmentLink::findPending($token))->toBeNull();
});

it('does not issue a link for a person who left or of another company', function (): void {
    $gone = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]);
    $foreign = EmployeeFactory::new()->create();

    expect(fn () => resolve(IssueEnrollment::class)->handle($gone->id, (string) $this->rh->id))->toThrow(EmployeeNotAvailable::class)
        ->and(fn () => resolve(IssueEnrollment::class)->handle($foreign->id, (string) $this->rh->id))->toThrow(EmployeeNotAvailable::class)
        ->and(EnrollmentLink::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('writes the email in «usted», with the one use and the 48 hours', function (): void {
    $mail = (new EnrollmentInvitation('https://humetria.test/a/acme/enroll/tok', 'Ana', new CompanySummary('t', 'Acme', 'acme', false)))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Vincule su teléfono para registrar su jornada')
        ->and($mail->actionText)->toBe('Vincular mi teléfono')
        ->and(implode(' ', [...$mail->introLines, ...$mail->outroLines]))->toContain('una sola vez')->toContain('48 horas')->toContain('no lo comparta');
});
