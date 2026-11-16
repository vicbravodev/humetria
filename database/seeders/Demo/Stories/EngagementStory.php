<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Engagement\Actions\CloseSurvey;
use App\Modules\Engagement\Actions\CreatePulseDraft;
use App\Modules\Engagement\Actions\CreateSurveyDraft;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\SubmitResponse;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;

/**
 * Clima y pulso at Alba del Bajío. Some four months ago the first climate survey closed with resources (staffing) and pay
 * as the weak spots, lowest in the plant's night shift. Two pulses since then followed those priorities and show the
 * engagement index going up. A third pulse opened five days ago and about 60 % have answered; the people reserved for the
 * door have their link pending. There is no pulse calendar: every pulse is launched by hand, so nothing launches by
 * itself and changes the story. Every answer goes through the person's link (SubmitResponse), so it is stored keyless.
 */
final class EngagementStory implements DemoStory
{
    private const string NIGHT_SHIFT = 'Producción turno 2';

    /** Questions answered with free text: the story writes no comments (they would go to the AI summary). */
    private const array OPEN = ['manager.keep', 'manager.change', 'open.best', 'open.improve'];

    /** How each topic leans against the wave's mood: staffing and pay low (the night shift's overtime), the team high. */
    private const array LEANING = ['resources' => -0.2, 'reward' => -0.15, 'communication' => -0.05, 'team' => 0.1, 'manager' => 0.05];

    /** The smallest group of answers results show (results.min_group). */
    private const int MIN_GROUP = 5;

    /** The coarse groups results are cut by, one at a time. */
    private const array CUTS = ['work_center_id', 'area_id', 'tenure_band', 'manager_group_id'];

    public function __construct(
        private readonly CreateSurveyDraft $climateDraft,
        private readonly CreatePulseDraft $pulseDraft,
        private readonly LaunchSurvey $launch,
        private readonly SubmitResponse $submit,
        private readonly CloseSurvey $close,
    ) {}

    public function tell(DemoContext $context): void
    {
        // Day it opens, days it stays open, its mood (the share of favorable answers) and how many answer.
        $this->wave($context, SurveyKind::Climate, -130, 14, 0.55, 0.88, closes: true);
        $this->wave($context, SurveyKind::Pulse, -75, 10, 0.63, 0.85, closes: true);
        $this->wave($context, SurveyKind::Pulse, -35, 10, 0.71, 0.86, closes: true);
        $this->wave($context, SurveyKind::Pulse, -5, 14, 0.74, 0.6, closes: false);
    }

    private function wave(DemoContext $context, SurveyKind $kind, int $day, int $open, float $mood, float $turnout, bool $closes): void
    {
        $survey = DemoClock::at("{$day} days", function () use ($context, $kind, $open): Survey {
            $draft = $kind === SurveyKind::Climate
                ? $this->climateDraft->handle($kind, $context->hr->id)
                : $this->pulseDraft->handle(PulseMode::FollowUp, $context->hr->id);
            $survey = $this->launch->handle($draft->id, CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay()->addDays($open), $context->hr->id);
            $this->markSent($survey);

            return $survey;
        });

        $people = $this->peopleById();
        $invitations = array_values(Invitation::query()->where('survey_id', $survey->id)->get()
            ->sortBy(fn (Invitation $invitation): string => DemoRoster::sortKey($people[$invitation->employee_id]->key))->all());
        // The open pulse leaves the door's links for prospects; anyone may have answered the closed waves.
        $answering = $this->answering($invitations, fn (Invitation $invitation): bool => $closes || ! $people[$invitation->employee_id]->reservedForDoor, $turnout);
        $answerDays = min($open, abs($day)) - 1;

        foreach ($invitations as $i => $invitation) {
            if (! isset($answering[$invitation->id])) {
                continue;
            }

            $person = $people[$invitation->employee_id];
            DemoClock::at(($day + 1 + $i % $answerDays).' days', fn () => $this->submit->handle(
                $invitation->surveyToken(), $this->answers($survey, $invitation, $person, $mood, $i), [], 4 + $i % 6,
            ));
        }

        if ($closes) {
            DemoClock::at(($day + $open).' days', fn () => $this->close->handle($survey->id, $context->hr->id));
        }
    }

    /** The invitation job would store each link's hash and mark it sent; it runs on the queue, so the story does it now. */
    private function markSent(Survey $survey): void
    {
        foreach (Invitation::query()->where('survey_id', $survey->id)->get() as $invitation) {
            $invitation->update(['token_hash' => Invitation::hashToken($invitation->surveyToken()), 'invited_at' => now(), 'invitation_status' => InvitationStatus::Sent]);
        }
    }

    /**
     * Who answers: a `$turnout` share of everyone, spread evenly over those `$may`, plus whoever a group needs so that every
     * group with answers has at least five. Who sits in a group where fewer than five may answer stays silent: that group
     * shows nothing rather than too little.
     *
     * @param  list<Invitation>  $invitations
     * @param  callable(Invitation): bool  $may
     * @return array<string, true> by invitation id
     */
    private function answering(array $invitations, callable $may, float $turnout): array
    {
        $eligible = array_values(array_filter($invitations, $may));

        do {
            $before = count($eligible);

            foreach (self::CUTS as $cut) {
                foreach ($this->groups($eligible, $cut) as $members) {
                    if (count($members) < self::MIN_GROUP) {
                        $eligible = array_values(array_filter($eligible, fn (Invitation $invitation): bool => ! in_array($invitation, $members, true)));
                    }
                }
            }
        } while (count($eligible) !== $before);

        $quota = min(count($eligible), (int) round(count($invitations) * $turnout));
        $chosen = [];

        foreach ($eligible as $i => $invitation) {
            if (intdiv(($i + 1) * $quota, count($eligible)) > intdiv($i * $quota, count($eligible))) {
                $chosen[$invitation->id] = true;
            }
        }

        // Only adding: each round can only fill groups up, so it ends.
        do {
            $before = count($chosen);

            foreach (self::CUTS as $cut) {
                foreach ($this->groups($eligible, $cut) as $members) {
                    $short = self::MIN_GROUP - count(array_filter($members, fn (Invitation $invitation): bool => isset($chosen[$invitation->id])));

                    foreach ($members as $invitation) {
                        if ($short > 0 && $short < self::MIN_GROUP && ! isset($chosen[$invitation->id])) {
                            $chosen[$invitation->id] = true;
                            $short--;
                        }
                    }
                }
            }
        } while (count($chosen) !== $before);

        return $chosen;
    }

    /**
     * @param  list<Invitation>  $invitations
     * @return array<string, list<Invitation>> the invitations of each group of the cut, leaving out the ungrouped
     */
    private function groups(array $invitations, string $cut): array
    {
        $groups = [];

        foreach ($invitations as $invitation) {
            $value = $invitation->getAttribute($cut);

            if (is_string($value)) {
                $groups[$value][] = $invitation;
            }
        }

        return $groups;
    }

    /**
     * A whole questionnaire: each question favorable (4 or 5; 9 or 10 to recommend) for a share of people that follows the
     * wave's mood and the question's topic, a bit lower in the night shift. No comments.
     *
     * @return array<string, int>
     */
    private function answers(Survey $survey, Invitation $invitation, DemoPerson $person, float $mood, int $i): array
    {
        $answers = [];

        foreach ($survey->items as $j => $ref) {
            $key = explode('@', $ref)[0];

            if (in_array($key, self::OPEN, true) || (str_starts_with($key, 'manager.') && $invitation->about_manager_id === null)) {
                continue;
            }

            $share = $mood + (self::LEANING[explode('.', $key)[0]] ?? 0.0) - ($person->area === self::NIGHT_SHIFT ? 0.1 : 0.0);
            $favorable = ($i * 7 + $j * 13) % 20 < (int) round(20 * min(0.9, max(0.1, $share)));
            $answers[$key] = $key === 'eng.recommend'
                ? ($favorable ? 9 + ($i + $j) % 2 : [5, 7, 8][($i + $j) % 3])
                : ($favorable ? 4 + ($i + $j) % 2 : 2 + ($i + $j) % 2);
        }

        return $answers;
    }

    /** @return array<string, DemoPerson> */
    private function peopleById(): array
    {
        $people = [];

        foreach (DemoRoster::people() as $person) {
            $people[DemoRoster::employeeId($person->key)] = $person;
        }

        return $people;
    }
}
