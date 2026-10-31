<?php

declare(strict_types=1);

namespace App\Modules\Telework\Listeners;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Telework\Actions\EndTelework;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Notifications\TeleworkMoved;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * The roster and the Comisión belong to a work center, so someone who moves ends their period there (`transfer`) and
 * the owner is told: in the new center they register again as an aspirant. Nothing open, nothing to do.
 */
final readonly class EndOnMove
{
    public function __construct(private Companies $companies, private EndTelework $end, private Roster $roster, private WorkCenters $workCenters) {}

    public function handle(EmployeeMoved $event): void
    {
        $company = $this->companies->enter($event->tenantId);
        $period = Teleworker::query()->where('employee_id', $event->employeeId)->where('status', '!=', TeleworkerStatus::Ended)->first();

        if ($period === null) {
            return;
        }

        $this->end->handle($period, EndReason::Transfer, CarbonImmutable::now(), null, null);

        $owner = $this->companies->contact();
        $person = $this->roster->find($event->employeeId);

        if ($owner !== null && $person !== null) {
            Notification::route('mail', $owner)->notify(new TeleworkMoved(
                $person->name,
                $this->workCenters->profile($event->fromWorkCenterId)->name ?? '',
                $this->workCenters->profile($event->toWorkCenterId)->name ?? '',
                route('tenant.telework.teleworkers.show', [$company->slug, $period->id]),
            ));
        }
    }
}
