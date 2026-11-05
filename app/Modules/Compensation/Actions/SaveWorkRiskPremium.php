<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class SaveWorkRiskPremium
{
    public function __construct(private CurrentTenant $currentTenant) {}

    /**
     * The IMSS work risk premium of the company from a date on; the one of the same date is replaced.
     *
     * @param  int  $rateScaled  the rate times 10^7 (see WorkRiskPremium)
     *
     * @throws ValidationException
     */
    public function handle(CarbonImmutable $effectiveFrom, int $rateScaled): WorkRiskPremium
    {
        if ($rateScaled < WorkRiskPremium::MINIMUM || $rateScaled > WorkRiskPremium::MAXIMUM) {
            throw ValidationException::withMessages(['rate' => 'La prima de riesgo va de 0.5 % a 15 %.']);
        }

        $premium = WorkRiskPremium::query()->updateOrCreate(
            ['tenant_id' => $this->currentTenant->id(), 'effective_from' => $effectiveFrom->toDateString()],
            ['rate_scaled' => $rateScaled],
        );

        Log::info('compensation.risk_premium_saved', ['risk_premium_id' => $premium->id]);

        return $premium;
    }
}
