<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * A module of a company as the console lists it: whether the company has it, where it comes from (plan, legacy, grant or revoke;
 * null when it has none) and, if that ends, the day it does.
 */
final readonly class ModuleRow
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $enabled,
        public ?string $source,
        public ?string $sourceLabel,
        public ?string $reason,
        public ?string $endsOn,
        public bool $existsInCode,
    ) {}
}
