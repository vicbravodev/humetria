<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Contracts;

use App\Modules\Announcements\Data\CompanyNotice;

/** What the platform tells a company's panel right now. Read on every page of the panel: it reads no table once warm. */
interface PlatformNotices
{
    /** What a company sees now: the platform's maintenance first, else the announcement that applies; null without either. */
    public function forCompany(string $tenantId): ?CompanyNotice;

    /** Whether the platform is in maintenance: every company's panel reads but changes nothing, its portals and surveys go on. */
    public function inMaintenance(): bool;
}
