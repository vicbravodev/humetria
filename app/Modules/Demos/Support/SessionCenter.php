<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Contracts\DemoSessionCenter;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Contracts\DemoCompanies;

final readonly class SessionCenter implements DemoSessionCenter
{
    public function __construct(private DemoCompanies $demoCompanies) {}

    public function is(string $workCenterId): bool
    {
        // Asking whether the company is the demo costs no query: a real company never reads the session.
        return $this->demoCompanies->isCurrent() && DemoSession::current()?->work_center_id === $workCenterId;
    }
}
