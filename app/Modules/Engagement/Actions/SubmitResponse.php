<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyUnavailableReason;
use App\Modules\Engagement\Exceptions\SurveyUnavailable;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Support\Bank\AnswerRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Takes a person's whole survey, once. The response keeps the coarse group copied from the invitation (never the
 * invitation's id) and each comment is stored apart; the only trace of who answered is the invitation marked answered.
 * Everything happens under the locks of OpenInvitation, so two submissions of the same link, or one racing the close,
 * cannot both pass.
 */
final readonly class SubmitResponse
{
    public const int DEADLOCK_ATTEMPTS = 3;

    public function __construct(private OpenInvitation $openInvitation) {}

    /**
     * @param  array<array-key, mixed>  $answers
     * @param  array<array-key, mixed>  $comments
     *
     * @throws SurveyUnavailable when the link no longer works.
     * @throws InvalidArgumentException when the answers are not the complete, coherent set of the questions shown.
     */
    public function handle(string $token, array $answers, array $comments, ?int $minutes): void
    {
        DB::transaction(function () use ($token, $answers, $comments, $minutes): void {
            $opened = $this->openInvitation->handle($token, forUpdate: true);
            $invitation = $opened->invitation;
            $checked = AnswerRules::check($opened->presented(), $answers, $comments);

            Response::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'survey_id' => $invitation->survey_id,
                'work_center_id' => $invitation->work_center_id,
                'area_id' => $invitation->area_id,
                'tenure_band' => $invitation->tenure_band,
                'manager_group_id' => $invitation->manager_group_id,
                'answers' => $checked['answers'],
                'minutes' => $minutes !== null && $minutes >= 1 && $minutes <= 60 ? $minutes : null,
            ]);

            foreach ($checked['comments'] as $key => $body) {
                Comment::query()->create([
                    'tenant_id' => $invitation->tenant_id,
                    'survey_id' => $invitation->survey_id,
                    'item_key' => $key,
                    'comment_group_id' => str_starts_with($key, 'manager.') ? $invitation->comment_group_id : null,
                    'body' => $body,
                ]);
            }

            // Only while still open: someone deactivated meanwhile has the link cancelled, and then nothing is kept.
            $taken = Invitation::query()->whereKey($invitation->id)->where('answered', false)->where('cancelled', false)->update(['answered' => true]);

            if ($taken === 0) {
                throw new SurveyUnavailable(SurveyUnavailableReason::Invalid, $opened->company);
            }

            Log::info('engagement.invitation_answered', ['invitation_id' => $invitation->id, 'survey_id' => $invitation->survey_id]);
            Log::info('engagement.response_recorded', ['survey_id' => $invitation->survey_id]);
        }, self::DEADLOCK_ATTEMPTS);
    }
}
