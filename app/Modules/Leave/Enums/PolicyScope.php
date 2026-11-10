<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * Whom a vacation policy is assigned to (criterio C-15). The most specific wins: job grade, then staff group, then the
 * default of the company.
 */
enum PolicyScope: string
{
    case Default = 'default';
    case StaffGroup = 'staff_group';
    case JobGrade = 'job_grade';
}
