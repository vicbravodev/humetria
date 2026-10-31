<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\Gate;

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

    /** The work center of the person who teleworks from this workplace. */
    public function workCenterOf(Workplace $workplace): string
    {
        return $workplace->teleworker()->firstOrFail()->work_center_id;
    }
}
