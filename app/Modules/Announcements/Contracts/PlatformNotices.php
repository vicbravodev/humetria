<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Contracts;

use App\Modules\Announcements\Data\CompanyNotice;

/** What the platform tells a company's panel right now. Read on every page of the panel: it reads no table once warm. */
interface PlatformNotices
{
    /** The announcement a company sees now; null when none applies. */
    public function forCompany(string $tenantId): ?CompanyNotice;
}
