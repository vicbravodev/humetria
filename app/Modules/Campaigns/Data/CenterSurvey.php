<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * A confidential survey of a work center as another module sees it: where it stands, its last day and how many people it
 * reached and how many answered. Never who.
 */
final readonly class CenterSurvey
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $closesOn,
        public int $participants,
        public int $answered,
    ) {}
}
