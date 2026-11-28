<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Jobs;

use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\FeedbackPublished;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells everyone who was invited to the survey, and is still active and has an email, that the company published what it
 * will do about it, with the address of its page (no credential: they enter first; the same for everyone). It runs in
 * the company that queued it. Only the survey's id travels. Publishing happens once, so this is queued once; if a worker
 * dies halfway a retry may repeat the emails of the block it was sending.
 */
final class SendFeedbackPublished implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    private const int BLOCK = 100; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(public readonly string $surveyId) {}

    public function handle(Roster $roster, WorkerLinks $links, Companies $companies): void
    {
        $survey = Survey::query()->find($this->surveyId);
        $company = $companies->current();

        if ($survey === null || $survey->results_published_at === null || $company->stopped($survey->kind->value)) {
            return;
        }

        $url = $links->to('portal.climate.show');

        Invitation::query()->where('survey_id', $survey->id)->where('cancelled', false)->orderBy('id')->toBase()->select('employee_id')
            ->chunk(self::BLOCK, function ($block) use ($roster, $url, $company): void {
                $ids = array_values($block->map(fn (object $row): string => is_string($row->employee_id) ? $row->employee_id : '')->filter()->all());

                foreach ($roster->findMany($ids) as $person) {
                    if ($person->active && ($person->email ?? '') !== '') {
                        Notification::route('mail', $person->email)->notify(new FeedbackPublished($url, $person->name, $company));
                    }
                }
            });
    }
}
