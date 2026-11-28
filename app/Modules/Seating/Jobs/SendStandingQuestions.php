<?php

declare(strict_types=1);

namespace App\Modules\Seating\Jobs;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Notifications\StandingQuestions;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Asks a person the 2 questions of their Ley Silla page while their assessment waits, with the address of their Ley Silla page (no credential: they enter first).
 * Only ids travel in the job. Nothing is sent once they answered after it started, when it is signed, when they have no
 * email or left, or when the company may not be used; a person has at most one waiting.
 */
final class SendStandingQuestions implements ShouldBeUniqueUntilProcessing, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** One email read from the database when it runs: a retry sends it once, after a mail server's hiccup. */
    public int $tries = 3; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300]; // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveArrayItem

    /** Seconds a person's waiting request blocks another one. */
    public int $uniqueFor = 600; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(public readonly string $tenantId, public readonly string $employeeId) {}

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->employeeId}";
    }

    public function handle(Companies $companies, Roster $roster, WorkerLinks $links): void
    {
        $company = $companies->enter($this->tenantId);
        $person = $roster->find($this->employeeId);

        if ($company->stopped('seating') || $person === null || ! $person->active || $person->email === null) {
            return;
        }

        $draft = RiskAssessment::query()->where('employee_id', $person->id)->where('status', AssessmentStatus::Draft)->first(['created_at']);

        if ($draft === null || PersonInput::query()->where('employee_id', $person->id)->where('submitted_at', '>=', $draft->created_at)->exists()) {
            return;
        }

        Notification::route('mail', $person->email)->notify(new StandingQuestions($company, $person->name, $links->to('portal.seating.show')));
        Log::info('seating.standing_questions_sent', ['employee_id' => $person->id]);
    }
}
