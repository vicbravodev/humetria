<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Actions\OpenSurvey;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Exceptions\SurveyUnavailable;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\ItemDefinition;
use App\Modules\Instruments\Data\SectionDefinition;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The employee's survey, reached from the emailed link. No account: the link's token is the credential.
 */
final class SurveyController extends Controller
{
    public function __construct(private readonly Instruments $instruments) {}

    public function show(string $token, OpenSurvey $openSurvey): Response
    {
        try {
            $survey = $openSurvey->handle($token);
        } catch (SurveyUnavailable $unavailable) {
            return Inertia::render('campaigns/survey', [
                'unavailable' => $unavailable->reason->value,
                'company' => $unavailable->company,
                'closesOn' => null,
                'instruments' => [],
            ]);
        }

        $statuses = $survey->participant->instruments()
            ->where('status', '!=', InstrumentStatus::Cancelled)
            ->get()
            ->mapWithKeys(fn (ParticipantInstrument $instrument): array => [$instrument->instrument => $instrument->status->value])
            ->all();
        $instruments = [];

        // In the campaign's order: Guía I first.
        foreach ($survey->campaign->instruments as $key) {
            if (isset($statuses[$key])) {
                $instruments[] = $this->instrument($this->instruments->definition($key), $statuses[$key]);
            }
        }

        return Inertia::render('campaigns/survey', [
            'unavailable' => null,
            'company' => $survey->company,
            'closesOn' => $survey->campaign->closes_on === null ? null : CampaignClock::day($survey->campaign->closes_on, isDate: true),
            'instruments' => $instruments,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instrument(InstrumentDefinition $definition, string $status): array
    {
        return [
            'key' => $definition->key,
            'title' => $definition->title,
            'shortTitle' => $definition->shortTitle,
            'identified' => $definition->identified,
            'status' => $status,
            'options' => $definition->options,
            'sections' => array_map(fn (SectionDefinition $section): array => [
                'title' => $section->title,
                'instruction' => $section->instruction,
                'gate' => $section->gate === null ? null : [
                    'key' => $section->gate->key,
                    'question' => $section->gate->question,
                    'instruction' => $section->gate->instruction,
                ],
                'items' => array_map(fn (ItemDefinition $item): array => ['number' => $item->number, 'text' => $item->text], $section->items),
            ], $definition->sections),
        ];
    }
}
