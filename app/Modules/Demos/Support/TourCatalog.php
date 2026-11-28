<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\Tours\AttendanceTour;
use App\Modules\Demos\Support\Tours\BaseTour;
use App\Modules\Demos\Support\Tours\ComplaintsTour;
use App\Modules\Demos\Support\Tours\EngagementTour;
use App\Modules\Demos\Support\Tours\HumiTour;
use App\Modules\Demos\Support\Tours\LeaveTour;
use App\Modules\Demos\Support\Tours\Nom035Tour;
use App\Modules\Demos\Support\Tours\Tour;
use LogicException;

/** The tours of a guided session and their steps: «El centro y su gente» first, then the chosen ones in their order. */
final readonly class TourCatalog
{
    /**
     * Every `data-tour` a step lights up, in one place: the panel's pages carry them (Task 10 adds the new ones). Some are
     * already there: work-centers-list, add-employee, new-evaluation, humi-launcher.
     */
    public const array TARGETS = [
        'work-centers-list', // @pest-mutate-ignore: RemoveArrayItem
        'add-employee', // @pest-mutate-ignore: RemoveArrayItem
        'new-evaluation', // @pest-mutate-ignore: RemoveArrayItem
        'launch-evaluation', // @pest-mutate-ignore: RemoveArrayItem
        'evaluation-progress', // @pest-mutate-ignore: RemoveArrayItem
        'close-evaluation', // @pest-mutate-ignore: RemoveArrayItem
        'attendance-center-mode', // @pest-mutate-ignore: RemoveArrayItem
        'attendance-add-zone', // @pest-mutate-ignore: RemoveArrayItem
        'attendance-issue-kiosk', // @pest-mutate-ignore: RemoveArrayItem
        'send-worker-access', // @pest-mutate-ignore: RemoveArrayItem
        'new-climate-survey', // @pest-mutate-ignore: RemoveArrayItem
        'complaint-channel-qr', // @pest-mutate-ignore: RemoveArrayItem
        'approve-leave-request', // @pest-mutate-ignore: RemoveArrayItem
        'humi-launcher', // @pest-mutate-ignore: RemoveArrayItem
    ];

    public function __construct(
        private BaseTour $base,
        private Nom035Tour $nom035,
        private AttendanceTour $attendance,
        private EngagementTour $engagement,
        private ComplaintsTour $complaints,
        private LeaveTour $leave,
        private HumiTour $humi,
    ) {}

    /**
     * @param  list<TourKey>  $tours  the chosen ones, in their order; «base» goes first whether chosen or not
     * @return list<TourStep>
     */
    public function for(array $tours): array
    {
        return array_merge(...array_map(fn (TourKey $tour): array => $this->tour($tour)->steps(), $this->withBase($tours)));
    }

    /**
     * The state of every step of `$tours` («base» first), by key, in their order: each tour reads its facts once for all its
     * steps; a step that waits for a page is done once the session saw it.
     *
     * @param  list<TourKey>  $tours
     * @return array<string, StepState>
     */
    public function states(array $tours, DemoSession $session): array
    {
        $states = [];

        foreach ($this->withBase($tours) as $key) {
            $tour = $this->tour($key);
            $detected = $tour->states($session);

            foreach ($tour->steps() as $step) {
                $states[$step->key] = $step->visit === null
                    ? $detected[$step->key] ?? throw new LogicException("No detector for {$step->key}.")
                    : new StepState(in_array($step->visit, $session->visited, true));
            }
        }

        return $states;
    }

    /** The step `$key` of the session's tours; null when it has none such. */
    public function step(DemoSession $session, string $key): ?TourStep
    {
        return array_find($this->for($this->toursOf($session)), fn (TourStep $step): bool => $step->key === $key);
    }

    /**
     * The tours the session shows, in its order.
     *
     * @return list<TourKey>
     */
    public function toursOf(DemoSession $session): array
    {
        return array_map(TourKey::from(...), $session->tours);
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        return $this->states([$step->tour], $session)[$step->key];
    }

    /**
     * A parameter a step's page needs besides the company: the session's center, or its evaluation (read with the steps'
     * states, not again); null when there is none yet.
     */
    public function parameter(string $name, DemoSession $session): ?string
    {
        return match ($name) {
            'workCenter' => $session->work_center_id,
            'campaign' => $this->nom035->campaignId($session),
            default => null,
        };
    }

    /**
     * The panel pages some step waits for: only these are worth recording as visited.
     *
     * @return list<string>
     */
    public function visits(): array
    {
        return array_values(array_filter(array_map(fn (TourStep $step): ?string => $step->visit, $this->for(TourKey::cases()))));
    }

    /**
     * @param  list<TourKey>  $tours
     * @return list<TourKey>
     */
    private function withBase(array $tours): array
    {
        return [TourKey::Base, ...array_values(array_filter($tours, fn (TourKey $tour): bool => $tour !== TourKey::Base))];
    }

    private function tour(TourKey $tour): Tour
    {
        return match ($tour) {
            TourKey::Base => $this->base,
            TourKey::Nom035 => $this->nom035,
            TourKey::Attendance => $this->attendance,
            TourKey::Engagement => $this->engagement,
            TourKey::Complaints => $this->complaints,
            TourKey::Leave => $this->leave,
            TourKey::Humi => $this->humi,
        };
    }
}
