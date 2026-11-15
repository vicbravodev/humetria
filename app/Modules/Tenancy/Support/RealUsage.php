<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\WorkCenter;
use InvalidArgumentException;

/** What a company really has of a limited resource, counted where it lives: the source the counters are started and fixed from. */
final readonly class RealUsage
{
    public function __construct(private Headcount $headcount) {}

    /** The resources a plan limits, in the order they are shown. */
    public const array RESOURCES = [CompanyLimits::SEATS, CompanyLimits::CENTERS];

    public function of(string $tenantId, string $resource): int
    {
        return match ($resource) {
            CompanyLimits::SEATS => $this->headcount->ofCompany($tenantId),
            CompanyLimits::CENTERS => WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('archived_at')->count(),
            default => throw new InvalidArgumentException("[{$resource}] is not a limited resource."),
        };
    }

    public static function label(string $resource): string
    {
        return $resource === CompanyLimits::SEATS ? 'Personas' : 'Centros de trabajo';
    }
}
