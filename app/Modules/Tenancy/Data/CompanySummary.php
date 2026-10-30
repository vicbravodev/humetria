<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A company as other modules show it. A blocked company (deactivated, or whose trial or term ran out) may not be used.
 * Its logo goes on the emails its people get; without one, they show its name.
 */
final readonly class CompanySummary
{
    public function __construct(public string $id, public string $tradeName, public bool $blocked, public ?CompanyLogo $logo = null) {}
}
