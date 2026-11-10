<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Data;

use App\Modules\Engagement\Enums\CommitmentGroup;
use Carbon\CarbonImmutable;

/** What a person of RH writes down as a commitment of the company, for the whole company or for one group of the results. */
final readonly class CommitmentData
{
    public function __construct(
        public string $surveyId,
        public int $userId,
        public CommitmentGroup $group,
        public ?string $groupId,
        public ?string $driver,
        public string $heard,
        public string $willDo,
        public string $ownerName,
        public CarbonImmutable $dueOn,
    ) {}
}
