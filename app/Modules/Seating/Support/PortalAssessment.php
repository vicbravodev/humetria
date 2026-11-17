<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;

/**
 * The person's current signed assessment, as their pages show it: whether it waits for their confirmation, when they gave
 * it, and what they are asked to confirm.
 */
final readonly class PortalAssessment
{
    private ?RiskAssessment $current;

    private ?RiskAcknowledgment $confirmed;

    public function __construct(string $employeeId)
    {
        $this->current = RiskAssessment::query()->where('employee_id', $employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first();
        $this->confirmed = $this->current === null ? null : RiskAcknowledgment::query()->where('assessment_id', $this->current->id)->first();
    }

    public function pending(): bool
    {
        return $this->current !== null && $this->confirmed === null;
    }

    /** The day the person confirmed their current assessment, in the display zone; null when they did not. */
    public function acknowledgedOn(): ?string
    {
        return $this->confirmed?->acknowledged_at->setTimezone(config()->string('app.display_timezone'))->toDateString();
    }

    /**
     * What the person is asked to confirm: the current signed assessment and its measures, until they do.
     *
     * @return array{assessmentId: string, level: string|null, levelLabel: string|null, signedOn: string|null, measures: list<string>}|null
     */
    public function toConfirm(): ?array
    {
        if ($this->current === null || $this->confirmed !== null) {
            return null;
        }

        return [
            'assessmentId' => $this->current->id,
            'level' => $this->current->level?->value,
            'levelLabel' => $this->current->level?->label(),
            'signedOn' => $this->current->signed_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            'measures' => $this->measuresOf($this->current),
        ];
    }

    /**
     * The measures that apply to the person: those of their assessment and those of their position, without the cancelled ones.
     *
     * @return list<string>
     */
    private function measuresOf(RiskAssessment $assessment): array
    {
        $kinds = PreventiveMeasure::query()->where('status', '!=', MeasureStatus::Cancelled)
            ->where(fn ($query) => $query->where('assessment_id', $assessment->id)->orWhere(fn ($own) => $own->whereNull('assessment_id')->where('position_id', $assessment->position_id)))
            ->orderBy('created_at')->get(['kind'])->map(fn (PreventiveMeasure $measure): string => $measure->kind->label());

        return array_values($kinds->unique()->all());
    }
}
