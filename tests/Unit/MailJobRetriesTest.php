<?php

declare(strict_types=1);

use App\Modules\Attendance\Jobs\SendEnrollmentLink;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Engagement\Jobs\SendFeedbackPublished;
use App\Modules\Results\Jobs\SendReferralReminder;
use App\Modules\Results\Jobs\SendSupportNotice;
use App\Modules\Seating\Jobs\SendRisksToConfirm;
use App\Modules\Seating\Jobs\SendStandingQuestions;
use App\Modules\Telework\Jobs\SendAccidentAlert;
use App\Modules\Telework\Jobs\SendNoticeAnswer;
use App\Modules\Telework\Jobs\SendPortalInvitation;
use App\Modules\Telework\Jobs\SendPortalReminder;

/**
 * A job that sends one email, after reading what it says from the database, can run again: a mail server that hiccups
 * gets two more chances, a minute and five minutes later, before the job fails for good (and queue.job_failed is logged).
 */
it('retries a job that sends one email', function (string $job): void {
    $instance = (new ReflectionClass($job))->newInstanceWithoutConstructor();

    expect($instance->tries)->toBe(3)
        ->and($instance->backoff)->toBe([60, 300]);
})->with([
    SendAccidentAlert::class,
    SendNoticeAnswer::class,
    SendPortalInvitation::class,
    SendPortalReminder::class,
    SendPolicyReminder::class,
    SendReferralReminder::class,
    SendSupportNotice::class,
    SendRisksToConfirm::class,
    SendStandingQuestions::class,
]);

/**
 * Not these. SendEnrollmentLink stores the link before mailing it, so a retry finds the link and skips the email; a batch
 * (SendFeedbackPublished) would send again to everyone before the one that failed.
 */
it('runs once a job that a retry would not repeat safely', function (string $job): void {
    expect(property_exists($job, 'tries'))->toBeFalse()
        ->and(property_exists($job, 'backoff'))->toBeFalse();
})->with([
    SendEnrollmentLink::class,
    SendFeedbackPublished::class,
]);
