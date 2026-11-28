<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * The views of the console's companies: all of them but the archived, or those in one status. Expired gathers a lapsed
 * payment and an ended trial: both are read only until the superadmin activates them. TrialEnding and Dormant are work
 * questions across statuses: trials that end within seven days, and companies nobody has entered in thirty.
 */
enum TenantView: string
{
    case All = 'all';
    case Active = 'active';
    case Trial = 'trial';
    case TrialEnding = 'trial_ending';
    case Dormant = 'dormant';
    case Expired = 'expired';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
