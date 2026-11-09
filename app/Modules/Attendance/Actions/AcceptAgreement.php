<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Records that a person accepted the version of the agreement they read, on the device they link, and when (criterio
 * 5.1). Only the current version: a person who read an older one reads the new one first.
 */
final readonly class AcceptAgreement
{
    public function __construct(private CurrentTenant $tenant, private CurrentAgreement $agreement) {}

    /** @throws AgreementOutdated when `$version` is not the company's current agreement */
    public function handle(string $employeeId, string $deviceId, int $version): AgreementAcceptance
    {
        $agreement = $this->agreement->get();

        if ($agreement->version !== $version) {
            throw new AgreementOutdated;
        }

        $acceptance = AgreementAcceptance::query()->create([
            'tenant_id' => $this->tenant->id(),
            'agreement_id' => $agreement->id,
            'employee_id' => $employeeId,
            'device_id' => $deviceId,
            'accepted_at' => CarbonImmutable::now(),
        ]);

        Log::info('attendance.agreement_accepted', ['acceptance_id' => $acceptance->id, 'employee_id' => $employeeId, 'device_id' => $deviceId, 'version' => $version]);

        return $acceptance;
    }
}
