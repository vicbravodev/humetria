<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Enums;

/** Who sees an announcement: every company, or the ones on one plan of the catalog. */
enum AnnouncementAudience: string
{
    case All = 'all';
    case Plan = 'plan';
}
