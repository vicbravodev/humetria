<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Data;

/** A survey created since a moment: whether it was launched or closed, how many it invited and how many invitations were answered. */
final readonly class SurveyActivity
{
    public function __construct(
        public bool $created,
        public bool $launched,
        public int $invited,
        public int $answered,
        public bool $closed,
    ) {}
}
