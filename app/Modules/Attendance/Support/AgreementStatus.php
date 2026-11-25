<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Whether a person accepted their company's current agreement (criterio 5.1): an older version does not count. */
final readonly class AgreementStatus
{
    public function __construct(private CurrentAgreement $agreement) {}

    public function accepted(string $employeeId): bool
    {
        return AgreementAcceptance::query()
            ->where('employee_id', $employeeId)
            ->where('agreement_id', $this->agreement->get()->id)
            ->exists();
    }

    /** The version of the current agreement the person accepted: null when they accepted only an older one, or none. */
    public function acceptedVersion(string $employeeId): ?int
    {
        $current = $this->agreement->get();

        return AgreementAcceptance::query()->where('employee_id', $employeeId)->where('agreement_id', $current->id)->exists() ? $current->version : null;
    }

    /**
     * Whether the person had accepted the agreement in force at `$at` (the ticket's issue of a mark made without a connection):
     * the version in force then is the highest one written by that moment, and an acceptance of it or of a later one counts. A
     * version published while the phone was without a connection does not reject an earlier mark. With no version yet at `$at`,
     * any acceptance counts.
     */
    public function acceptedAt(string $employeeId, CarbonImmutable $at): bool
    {
        $inForce = AttendanceAgreement::query()->where('created_at', '<=', $at)->max('version');

        return AgreementAcceptance::query()
            ->where('employee_id', $employeeId)
            ->when($inForce !== null, fn (Builder $query) => $query->whereIn('agreement_id', AttendanceAgreement::query()->select('id')->where('version', '>=', $inForce)))
            ->exists();
    }
}
