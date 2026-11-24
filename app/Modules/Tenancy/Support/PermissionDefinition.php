<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Module;

/** One permission of the company panel: what the roles screen shows and when it applies. */
final readonly class PermissionDefinition
{
    public function __construct(
        public string $name,
        public string $label,
        public string $module,
        public string $description,
        public bool $sensitive = false,
        public bool $ownerOnly = false,
        public ?Module $requiresModule = null,
        public bool $unionAllowed = false,
    ) {}
}
