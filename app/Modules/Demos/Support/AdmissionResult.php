<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

/** What the door answers a visitor: their worker's portal, a full room, or a door that is not open (never why). */
final readonly class AdmissionResult
{
    /**
     * @param  'admitted'|'full'|'closed'  $state
     */
    private function __construct(
        public string $state,
        public ?string $assignmentId = null,
        public ?string $employeeId = null,
        public ?string $portalUrl = null,
    ) {}

    public static function admitted(string $assignmentId, string $employeeId, string $portalUrl): self
    {
        return new self('admitted', $assignmentId, $employeeId, $portalUrl);
    }

    public static function full(): self
    {
        return new self('full');
    }

    public static function closed(): self
    {
        return new self('closed');
    }
}
