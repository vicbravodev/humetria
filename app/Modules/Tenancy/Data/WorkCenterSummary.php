<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class WorkCenterSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $archived,
    ) {}
}
