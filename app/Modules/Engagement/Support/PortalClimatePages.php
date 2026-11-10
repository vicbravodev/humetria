<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Telework\Data\PortalIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * «Nos dijeron / Haremos» as a person reads it in their portal: how many answered and how the company stands, if it is
 * big enough to say so, and the commitments of the company and of the groups the person was invited in. Never a response,
 * a comment, or a result of a group: the person reads what will be done, not what a group answered.
 */
final readonly class PortalClimatePages
{
    public const int PER_PAGE = 5;

    /** The survey whose commitments the portal shows: the last one published that still has a commitment to read. */
    public function survey(): ?Survey
    {
        return Survey::query()
            ->where('kind', SurveyKind::Climate)
            ->whereNotNull('results_published_at')
            ->whereExists(Commitment::query()->select('id')->whereColumn('survey_id', 'engagement_surveys.id')->where('status', '!=', CommitmentStatus::Dropped)->toBase())
            ->latest('results_published_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function props(Survey $survey, PortalIdentity $person, string $token): array
    {
        $results = SurveyResults::for($survey);
        $company = $results->company();
        $invited = $survey->invited ?? 0;
        $shown = resolve(Thresholds::class)->met($company->responses);

        return [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'survey' => ['name' => $survey->name],
            'participation' => $shown && $invited > 0 ? ['percent' => min(100, intdiv(200 * $company->responses + $invited, 2 * $invited))] : null,
            'index' => $shown ? $company->index : null,
            'commitments' => $this->commitments($survey, $person->employeeId),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function commitments(Survey $survey, string $employeeId): LengthAwarePaginator
    {
        $segment = Invitation::query()->where('survey_id', $survey->id)->where('employee_id', $employeeId)->first(['id', 'work_center_id', 'area_id', 'manager_group_id']);
        $own = [
            CommitmentGroup::WorkCenter->value => $segment?->work_center_id,
            CommitmentGroup::Area->value => $segment?->area_id,
            CommitmentGroup::Manager->value => $segment?->manager_group_id,
        ];
        $today = SurveyClock::day();

        return Commitment::query()
            ->where('survey_id', $survey->id)
            ->where('status', '!=', CommitmentStatus::Dropped)
            ->where(function (Builder $query) use ($own): void {
                $query->where('group_type', CommitmentGroup::Company);

                foreach ($own as $type => $id) {
                    if ($id !== null) {
                        $query->orWhere(fn (Builder $group) => $group->where('group_type', $type)->where('group_id', $id));
                    }
                }
            })
            ->orderByRaw("case group_type when 'company' then 0 when 'work_center' then 1 when 'area' then 2 else 3 end")
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Commitment $commitment): array => $this->row($commitment, $today));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Commitment $commitment, CarbonImmutable $today): array
    {
        return [
            'id' => $commitment->id,
            'group_name' => $this->groupName($commitment->group_type),
            'driver_label' => $commitment->driver === null ? null : Driver::from($commitment->driver)->label(),
            'heard' => $commitment->heard,
            'will_do' => $commitment->will_do,
            'owner_name' => $commitment->owner_name,
            'due_on' => $commitment->due_on->toDateString(),
            'status' => $commitment->status->value,
            'status_label' => $commitment->status->label(),
            'overdue' => $this->overdue($commitment, $today),
        ];
    }

    private function groupName(CommitmentGroup $group): string
    {
        return match ($group) {
            CommitmentGroup::Company => 'Toda la empresa',
            CommitmentGroup::WorkCenter => 'Su centro de trabajo',
            CommitmentGroup::Area => 'Su área',
            CommitmentGroup::Manager => 'Su equipo',
        };
    }

    private function overdue(Commitment $commitment, CarbonImmutable $today): bool
    {
        return $commitment->due_on->lessThan($today) && in_array($commitment->status, [CommitmentStatus::Open, CommitmentStatus::InProgress], true);
    }
}
