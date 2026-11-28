<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\WorkerAccess\Contracts\WorkerAccessActivity;
use App\Modules\WorkerAccess\Data\AccessActivity;
use Carbon\CarbonImmutable;

/**
 * WorkerAccess's activity, read once for the tours of one catalog: Asistencia and Vacaciones both ask what happened since
 * the session started. It lives as long as its catalog (one per guide), so it never outlives the read.
 */
final class SharedAccessActivity implements WorkerAccessActivity
{
    /** @var array<string, AccessActivity> by instant */
    private array $read = [];

    public function __construct(private readonly WorkerAccessActivity $access) {}

    public function since(CarbonImmutable $since): AccessActivity
    {
        return $this->read[$since->toIso8601ZuluString('microsecond')] ??= $this->access->since($since);
    }
}
