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
        'work-centers-list',
        'add-employee',
        'new-evaluation',
        'launch-evaluation',
        'evaluation-progress',
        'close-evaluation',
        'attendance-center-mode',
        'attendance-add-zone',
        'attendance-issue-kiosk',
        'send-worker-access',
        'new-climate-survey',
        'complaint-channel-qr',
        'approve-leave-request',
        'humi-launcher',
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
