<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Contracts\WorkerAccessActivity;
use App\Modules\WorkerAccess\Data\AccessActivity;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;

final readonly class CompanyWorkerAccessActivity implements WorkerAccessActivity
{
    public function since(CarbonImmutable $since): AccessActivity
    {
        // A bound date loses its offset: compare in UTC, as the columns are kept.
        $since = $since->utc();

        return new AccessActivity(
            WorkerAccessLink::query()->where('kind', LinkKind::Issued)->where('created_at', '>=', $since)->distinct()->count('employee_id'),
            WorkerCredential::query()->where('registered_at', '>=', $since)->whereNull('revoked_at')->count(),
        );
    }
}
