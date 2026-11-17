<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

/**
 * The IANA zones of the current company (design: docs/superpowers/specs/2026-11-17-timezones-design.md). Instants are stored
 * in UTC; a module converts with these only to show them or to read a local date. The working day, the shift and
 * compliance are measured in the work center; what is the company's and not a center's, in the company's zone.
 */
interface Timezones
{
    /** The zone of a work center of the company, archived ones included; the company's when it is not one of its. */
    public function forCenter(string $workCenterId): string;

    /**
     * The zones of several work centers at once, each as forCenter() gives it.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, string> by work center id, every one asked for
     */
    public function forCenters(array $workCenterIds): array;

    /** The company's own zone. */
    public function forTenant(): string;
}
