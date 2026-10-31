<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Who may judge a workplace's evidence. Deciding, asking for evidence and recording a visit are for whoever holds the
 * review permission AND sits on the Comisión of the person's work center: RH who is not on it is told no (403), and
 * anyone else (another center's Comisión, a viewer) is told the page does not exist (404).
 */
final readonly class ReviewAccess
{
    public function __construct(private CommitteeGate $gate) {}

    public function member(int $userId, string $workCenterId): void
    {
        abort_unless(Gate::allows('review-workplaces'), 404);

        if (! $this->gate->isMember($userId, $workCenterId)) {
            abort(Gate::allows('manage-telework') ? 403 : 404);
        }
    }

    /**
     * The assigned Comisión of the center, or RH. A viewer is refused outright; the Comisión of another center does not
     * learn that it exists.
     */
    public function reader(int $userId, string $workCenterId): void
    {
        abort_unless(Gate::allows('view-telework'), 403);

        if (! Gate::allows('manage-telework') && ! $this->gate->isMember($userId, $workCenterId)) {
            abort(Gate::allows('review-workplaces') ? 404 : 403);
        }
    }

    /** For a FormRequest: the gate of `member()` for the checklist in the address, before anything is validated. */
    public function ofApplication(int $userId, mixed $id): void
    {
        $application = is_string($id) && Str::isUuid($id) ? ChecklistApplication::query()->find($id) : null;
        abort_if($application === null, 404);
        $this->member($userId, $this->workCenterOf(Workplace::query()->findOrFail($application->workplace_id)));
    }

    /** For a FormRequest: the gate of `member()` for the workplace in the address. */
    public function ofWorkplace(int $userId, mixed $id): void
    {
        $workplace = is_string($id) && Str::isUuid($id) ? Workplace::query()->find($id) : null;
        abort_if($workplace === null, 404);
        $this->member($userId, $this->workCenterOf($workplace));
    }

    /** For a FormRequest: the gate of `reader()` for the visit authorization in the address. */
    public function readerOfAuthorization(int $userId, mixed $id): void
    {
        $authorization = is_string($id) && Str::isUuid($id) ? VisitAuthorization::query()->find($id) : null;
        abort_if($authorization === null, 404);
        $this->reader($userId, $this->workCenterOf(Workplace::query()->findOrFail($authorization->workplace_id)));
    }

    /** The work center of the person who teleworks from this workplace. */
    public function workCenterOf(Workplace $workplace): string
    {
        return $workplace->teleworker()->firstOrFail()->work_center_id;
    }
}
