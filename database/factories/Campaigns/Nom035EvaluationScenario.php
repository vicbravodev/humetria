<?php

declare(strict_types=1);

namespace Database\Factories\Campaigns;

use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\CreateEvaluationDraft;
use App\Modules\Campaigns\Actions\LaunchCampaign;
use App\Modules\Campaigns\Actions\SubmitSurvey;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignInstruments;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\ItemDefinition;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Takes the NOM-035 evaluation of a work center to a state, through the same actions a company uses: draft, launch,
 * answer by link and close. The answers are built to score exactly the final scores it is given, so what the results
 * must show is known beforehand. The company must already be the current one (`Companies::enter()`), and the
 * invitation job should be faked: the links are marked as sent here, as the job would.
 */
final class Nom035EvaluationScenario
{
    /** @var array<string, string> "guide.item.points" => the option that gives them (Tablas 2 and 5), filled on first use. */
    private array $scale = [];

    /**
     * @param  list<int>  $riskTotals  the final score of each person who answers Guía II or III, in order
     * @param  list<list<int>>  $guideOneYes  the "yes" answers of each Guía I scenario, handed out in turn
     */
    public function __construct(private readonly array $riskTotals, private readonly array $guideOneYes) {}

    /**
     * @param  'draft'|'active'|'closed'  $state
     * @param  int  $guideOneStart  the position of the first person who answers Guía I; the risk guide is answered from the first
     * @return list<string> survey links of people who have answered nothing, to open the whole survey (only while active)
     */
    public function run(string $workCenterId, bool $sample, string $state, int $createdBy, int $guideOneStart, int $guideOne, int $risk): array
    {
        $campaign = resolve(CreateEvaluationDraft::class)->handle($workCenterId, $sample ? Population::Sample : Population::All, $createdBy);

        if ($state === 'draft') {
            return [];
        }

        resolve(LaunchCampaign::class)->handle($campaign, CarbonImmutable::now()->addDays(14)->startOfDay());
        $participants = $this->sendLinks($campaign);
        $this->answer($campaign, $participants, $guideOneStart, $guideOne, $risk);

        if ($state === 'closed') {
            resolve(CloseCampaign::class)->handle(Campaign::query()->findOrFail($campaign->id));

            return [];
        }

        return $this->untouchedLinks($campaign);
    }

    /**
     * The invitation job would store each link's hash and mark it sent; it is faked, so this does it.
     *
     * @return list<Participant>
     */
    private function sendLinks(Campaign $campaign): array
    {
        $participants = Participant::query()->where('campaign_id', $campaign->id)->orderBy('id')->get();

        foreach ($participants as $participant) {
            $participant->update(['token_hash' => Participant::hashToken($participant->surveyToken()), 'invited_at' => now(), 'invitation_status' => InvitationStatus::Sent]);
        }

        return array_values($participants->all());
    }

    /**
     * @param  list<Participant>  $participants
     */
    private function answer(Campaign $campaign, array $participants, int $guideOneStart, int $guideOne, int $risk): void
    {
        $riskKey = array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');
        $submit = resolve(SubmitSurvey::class);
        $scenario = 0;
        $answered = 0;

        foreach (array_slice($participants, $guideOneStart, $guideOne) as $participant) {
            $submit->handle($participant->surveyToken(), 'nom035.guide-i', $this->guideOneAnswers($this->guideOneYes[$scenario++ % count($this->guideOneYes)]));
        }

        foreach ($participants as $participant) {
            if ($riskKey === null || $answered === $risk || ! in_array($riskKey, CampaignInstruments::for($campaign, $participant->in_sample), true)) {
                continue;
            }

            $submit->handle($participant->surveyToken(), $riskKey, $this->riskAnswers($riskKey, $this->riskTotals[$answered] ?? throw new InvalidArgumentException('There are more people than scores.'), $answered));
            $answered++;
        }
    }

    /**
     * @return list<string>
     */
    private function untouchedLinks(Campaign $campaign): array
    {
        $untouched = Participant::query()->where('campaign_id', $campaign->id)->whereDoesntHave('instruments', fn ($guides) => $guides->where('status', InstrumentStatus::Completed))->orderBy('id')->limit(3)->get();

        return array_values($untouched->map(fn (Participant $participant): string => route('survey.show', $participant->surveyToken()))->all());
    }

    /**
     * @param  list<int>  $yes
     * @return array<int, string>
     */
    private function guideOneAnswers(array $yes): array
    {
        $last = array_intersect($yes, range(1, 6)) === [] ? 6 : 20;
        $answers = [];

        foreach (range(1, $last) as $number) {
            $answers[$number] = in_array($number, $yes, true) ? 'yes' : 'no';
        }

        return $answers;
    }

    /**
     * A complete Guía II or III that scores exactly `$total` points: the points are spread over the items and each
     * item gets the option that gives them, so reverse-scored items are answered the other way. Some people say "no"
     * to the clients or to the supervision block when the score still fits in the rest.
     *
     * @return array<array-key, string> item number => option, and the two gates
     */
    private function riskAnswers(string $key, int $total, int $index): array
    {
        $items = [];
        $gated = [];

        foreach (resolve(Instruments::class)->definition($key)->sections as $section) {
            $numbers = array_map(fn (ItemDefinition $item): int => $item->number, $section->items);

            if ($section->gate === null) {
                $items = [...$items, ...$numbers];
            } else {
                $gated[$section->gate->key] = $numbers;
            }
        }

        $serves = ! ($index % 3 === 1 && $total <= 4 * (count($items) + count($gated['supervises'] ?? [])));
        $supervises = ! ($index % 4 === 2 && $total <= 4 * (count($items) + ($serves ? count($gated['serves_clients'] ?? []) : 0)));
        $items = [...$items, ...($serves ? $gated['serves_clients'] ?? [] : []), ...($supervises ? $gated['supervises'] ?? [] : [])];

        if ($total > 4 * count($items)) {
            throw new InvalidArgumentException("A score of {$total} does not fit in {$key}.");
        }

        $each = intdiv($total, count($items));
        $extra = $total % count($items);
        $answers = [];

        foreach ($items as $position => $number) {
            $points = $each + (($position + $index) % count($items) < $extra ? 1 : 0);
            $answers[(string) $number] = $this->optionFor($key, $number, $points);
        }

        // Assigned, never spread: a spread renumbers the numeric item keys.
        $answers['serves_clients'] = $serves ? 'yes' : 'no';
        $answers['supervises'] = $supervises ? 'yes' : 'no';

        return $answers;
    }

    private function optionFor(string $key, int $item, int $points): string
    {
        if ($this->scale === []) {
            $instruments = resolve(Instruments::class);

            foreach (['nom035.guide-ii' => 46, 'nom035.guide-iii' => 72] as $guide => $count) {
                foreach ($instruments->definition($guide)->options as $option) {
                    foreach (range(1, $count) as $number) {
                        $this->scale["{$guide}.{$number}.{$instruments->itemScore($guide, $number, $option['value'])}"] = $option['value'];
                    }
                }
            }
        }

        return $this->scale["{$key}.{$item}.{$points}"] ?? throw new InvalidArgumentException("No option of {$key} item {$item} gives {$points} points.");
    }
}
