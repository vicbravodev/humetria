<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * What the page of a Programa shows. The figures are Results' (read live, under the minimum in force); the Programa adds
 * only what HR wrote. Never an answer, an employee or an id of one.
 */
final readonly class ProgramPages
{
    public function __construct(private RiskResults $results, private WorkCenters $workCenters, private Instruments $instruments) {}

    /**
     * @return array<string, mixed>
     */
    public function show(CampaignRecord $campaign, ?Program $program, bool $canManage): array
    {
        $guide = array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');

        return [
            'campaign' => [
                'id' => $campaign->id,
                'workCenter' => $this->workCenters->profile($campaign->workCenterId)->name ?? '',
                'kindLabel' => $campaign->kindLabel,
                'closedOn' => $campaign->closedOn,
                'guide' => $guide === null ? null : $this->instruments->definition($guide)->shortTitle,
            ],
            'requirement' => $program === null ? $this->requirement($campaign) : null,
            'program' => $program === null ? null : $this->program($program),
            'can' => ['manage' => $canManage],
        ];
    }

    /**
     * Why there is no Programa yet: the results ask for one, ask for none, or are under the minimum.
     *
     * @throws ProgramUnavailable when the evaluation still runs or applies only the Guía I.
     */
    private function requirement(CampaignRecord $campaign): string
    {
        $risk = $this->results->forCampaign($campaign);

        return match (true) {
            in_array($risk->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true) => throw new ProgramUnavailable,
            $risk->state === RiskBreakdown::BELOW_MINIMUM => 'below_minimum',
            $risk->needingProgram() === [] => 'not_required',
            default => 'required',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function program(Program $program): array
    {
        return [
            'id' => $program->id,
            'highestLevel' => self::level($program->highest_level),
            'responsible' => $program->responsible_name,
            'items' => $program->items()->get()
                ->sortBy(fn (ProgramItem $item): string => self::order($item))
                ->map(fn (ProgramItem $item): array => [
                    'id' => $item->id,
                    'scope' => $item->scope->value,
                    'label' => $item->label,
                    'category' => $item->category_key,
                    'level' => self::level($item->highest_level),
                    'manual' => $item->origin === ItemOrigin::Manual,
                ])->values()->all(),
        ];
    }

    /** Final score first, then categories, then domains; by label inside each. */
    private static function order(ProgramItem $item): string
    {
        $place = match ($item->scope) {
            ItemScope::Final => 0,
            ItemScope::Category => 1,
            ItemScope::Domain => 2,
        };

        return "{$place}|{$item->label}";
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function level(?RiskLevel $level): ?array
    {
        return $level === null ? null : ['value' => $level->value, 'label' => $level->label()];
    }
}
