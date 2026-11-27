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
        $chosen = array_filter($tours, fn (TourKey $tour): bool => $tour !== TourKey::Base);

        return array_merge(...array_map(fn (TourKey $tour): array => $this->tour($tour)->steps(), [TourKey::Base, ...$chosen]));
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        return $this->tour($step->tour)->state($step, $session);
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
