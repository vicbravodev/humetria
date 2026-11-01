<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Telework\Enums\ReviewPeriod;
use App\Modules\Telework\Models\Consent;
use App\Modules\Telework\Models\TeleworkSettings;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * The NOM-037 settings of the current company. The first read creates the defaults: a 12-month period and the base
 * consent as version 1.
 */
final readonly class Settings
{
    public function __construct(private CurrentTenant $tenant, private Nom037Checklist $checklist) {}

    public function periodMonths(): int
    {
        return $this->record()->review_period_months;
    }

    public function record(): TeleworkSettings
    {
        // Read first, so the usual case is one query; createOrFirst settles two first reads racing.
        return TeleworkSettings::query()->first()
            ?? TeleworkSettings::query()->createOrFirst(['tenant_id' => $this->tenant->id()], ['review_period_months' => ReviewPeriod::DEFAULT->value]);
    }

    public function currentConsent(): Consent
    {
        return Consent::query()->orderByDesc('version')->first()
            ?? Consent::query()->createOrFirst(['tenant_id' => $this->tenant->id(), 'version' => 1], ['text' => $this->checklist->baseConsent()]);
    }
}
