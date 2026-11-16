<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use Carbon\CarbonInterface;

/** What the door answers a visitor: a way in as their worker, a full room, or a door that is not open (never why). */
final readonly class AdmissionResult
{
    /**
     * @param  'admitted'|'full'|'closed'  $state
     */
    private function __construct(
        public string $state,
        public ?string $assignmentId = null,
        public ?string $employeeId = null,
        public ?string $entryUrl = null,
        public ?CarbonInterface $until = null,
    ) {}

    /** @param  CarbonInterface  $until  when the session that let the visitor in ends */
    public static function admitted(string $assignmentId, string $employeeId, string $entryUrl, CarbonInterface $until): self
    {
        return new self('admitted', $assignmentId, $employeeId, $entryUrl, $until);
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
