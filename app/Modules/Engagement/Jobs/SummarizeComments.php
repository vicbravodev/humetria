<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Jobs;

use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Engagement\Agents\CommentScreener;
use App\Modules\Engagement\Agents\CommentSummarizer;
use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SensitiveMentions;
use App\Modules\Engagement\Support\Comments\CommentsBlock;
use App\Modules\Engagement\Support\Comments\SummaryGuard;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Summarizes, once, the comments of a group of a closed survey: the comments about the company (`$groupId` null) or the
 * ones about a manager. A ready summary is never made again. Whatever is wrong (AI off, too few comments, a failure) leaves
 * a status and never the original text as a fallback. Comments that describe harassment or violence are taken out in code
 * before summarizing, and the survey remembers there were some.
 */
final class SummarizeComments implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $surveyId, public readonly ?string $groupId) {}

    public function handle(): void
    {
        $survey = Survey::query()->find($this->surveyId);
        $thresholds = resolve(Thresholds::class);

        if ($survey === null || $this->row()?->status === SummaryStatus::Ready) {
            return;
        }

        $comments = $this->comments();

        // Under the minimum there is no group to speak of: not even a row that says so.
        if (! $thresholds->comments(count($comments['valued']) + count($comments['change']))) {
            return;
        }

        $this->mark($survey, SummaryStatus::Pending);
        $ai = resolve(AiAccess::class);

        if (! $ai->available()) {
            $this->mark($survey, SummaryStatus::AiOff);

            return;
        }

        $comments = $this->withoutSensitive($survey, $comments, $ai);

        if (! $thresholds->comments(count($comments['valued']) + count($comments['change']))) {
            $this->mark($survey, SummaryStatus::Withheld);

            return;
        }

        $themes = $this->summarize($comments, $ai);
        $this->mark($survey, SummaryStatus::Ready, $themes);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 600, 3600];
    }

    public function failed(?Throwable $exception): void
    {
        $survey = Survey::query()->find($this->surveyId);

        if ($survey !== null) {
            $this->mark($survey, SummaryStatus::Failed);
        }

        // The class only: the message of a provider error could carry the text it was sent.
        Log::warning('engagement.summary_failed', ['survey_id' => $this->surveyId, 'group_id' => $this->groupId, 'exception' => $exception === null ? null : $exception::class]);
    }

    /**
     * @param  array{valued: list<string>, change: list<string>}  $comments
     * @return array{valued: list<string>, change: list<string>}
     */
    private function withoutSensitive(Survey $survey, array $comments, AiAccess $ai): array
    {
        $all = [...$comments['valued'], ...$comments['change']];
        $flagged = $this->screen($all, $ai);

        if ($flagged === []) {
            return $comments;
        }

        $this->noteMentions($survey);
        $taken = array_flip($flagged);
        $offset = count($comments['valued']);

        return [
            'valued' => array_values(array_diff_key($comments['valued'], $taken)),
            // The numbers run through both lists: the second one starts where the first ends.
            'change' => array_values(array_filter($comments['change'], fn (int $index): bool => ! isset($taken[$offset + $index]), ARRAY_FILTER_USE_KEY)),
        ];
    }

    /** The survey remembers there were mentions, and whoever manages the complaints channel is told the first time only. */
    private function noteMentions(Survey $survey): void
    {
        if (Survey::query()->whereKey($survey->id)->where('sensitive_mentions', false)->update(['sensitive_mentions' => true]) === 0) {
            return;
        }

        $company = resolve(Companies::class)->current();
        $url = route('tenant.complaints.channel.show', $company->slug);

        foreach (resolve(Members::class)->complaintManagers() as $manager) {
            Notification::route('mail', $manager->email)->notify(new SensitiveMentions($company, $survey->name, $url));
        }

        Log::info('engagement.sensitive_mentions_noticed', ['survey_id' => $survey->id]);
    }

    /**
     * The numbers of the comments that describe harassment or violence.
     *
     * @param  list<string>  $all
     * @return list<int>
     */
    private function screen(array $all, AiAccess $ai): array
    {
        $response = $this->ask(CommentScreener::make(), 'engagement_comment_screener', CommentsBlock::build($all, $ai->redact(...)), $ai);
        $sensitive = $response->toArray()['sensitive'] ?? [];

        return is_array($sensitive) ? array_values(array_unique(array_filter($sensitive, fn (mixed $n): bool => is_int($n) && $n >= 0 && $n < count($all)))) : [];
    }

    /**
     * @param  array{valued: list<string>, change: list<string>}  $comments
     * @return array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}
     */
    private function summarize(array $comments, AiAccess $ai): array
    {
        $redact = $ai->redact(...);
        $prompt = "Lo que valoran:\n".CommentsBlock::build($comments['valued'], $redact)."\n\nLo que cambiarían:\n".CommentsBlock::build($comments['change'], $redact);
        $response = $this->ask(CommentSummarizer::make(), 'engagement_comment_summarizer', $prompt, $ai);

        return SummaryGuard::clean($response->toArray(), [...$comments['valued'], ...$comments['change']], config()->integer('engagement.min_theme_support'));
    }

    private function ask(CommentScreener|CommentSummarizer $agent, string $name, string $prompt, AiAccess $ai): StructuredAgentResponse
    {
        $started = hrtime(true);
        $response = $agent->prompt($prompt);
        $ai->record($name, $response->usage->inputTokens, $response->usage->outputTokens, intdiv((int) (hrtime(true) - $started), 1_000_000));

        return $response;
    }

    /**
     * The texts of the group: what they value (the first question) and what they would change (the second).
     *
     * @return array{valued: list<string>, change: list<string>}
     */
    private function comments(): array
    {
        [$valued, $change] = $this->groupId === null ? ['open.best', 'open.improve'] : ['manager.keep', 'manager.change'];
        $found = ['valued' => [], 'change' => []];

        // By id, which is a random UUID: neither the order of arrival nor of the people shows.
        $rows = Comment::query()->where('survey_id', $this->surveyId)->where('comment_group_id', $this->groupId)->whereIn('item_key', [$valued, $change])->orderBy('id')->get(['item_key', 'body']);
        foreach ($rows as $row) {
            $found[$row->item_key === $valued ? 'valued' : 'change'][] = $row->body;
        }

        return $found;
    }

    private function row(): ?CommentSummary
    {
        return CommentSummary::query()->where('survey_id', $this->surveyId)->where('manager_group_id', $this->groupId)->first();
    }

    /**
     * @param  array{valued: list<array{theme: string, count: int}>, change: list<array{theme: string, count: int}>}|null  $themes
     */
    private function mark(Survey $survey, SummaryStatus $status, ?array $themes = null): void
    {
        CommentSummary::query()->updateOrCreate(
            ['survey_id' => $survey->id, 'manager_group_id' => $this->groupId],
            ['tenant_id' => $survey->tenant_id, 'status' => $status, 'themes' => $themes, 'generated_on' => $themes === null ? null : now()->toDateString()],
        );

        Log::info('engagement.summary_marked', ['survey_id' => $survey->id, 'group_id' => $this->groupId, 'status' => $status->value]);
    }
}
