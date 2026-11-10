<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

/**
 * Who answers or cancels a request: a manager or the person who asked, from their portal, RH from the panel, or the
 * system with the reach of RH (when a person leaves).
 */
final readonly class Decider
{
    private function __construct(public ?string $employeeId, public ?int $userId, public bool $isHr) {}

    public static function manager(string $employeeId): self
    {
        return new self($employeeId, null, false);
    }

    public static function requester(string $employeeId): self
    {
        return new self($employeeId, null, false);
    }

    public static function hr(int $userId): self
    {
        return new self(null, $userId, true);
    }

    public static function system(): self
    {
        return new self(null, null, true);
    }
}
