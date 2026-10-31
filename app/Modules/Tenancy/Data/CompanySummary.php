<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A company as other modules show it. A blocked company (deactivated, or whose trial or term ran out) may not be used.
 * Its logo goes on the emails its people get; without one, they show its name.
 */
final readonly class CompanySummary
{
    /**
     * @param  string  $slug  Its address under /admin/, for links into it.
     */
    public function __construct(public string $id, public string $tradeName, public string $slug, public bool $blocked, public ?CompanyLogo $logo = null) {}
}
