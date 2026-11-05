<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * The notices of a risk of work (6.9) of a work center nobody reviewed yet: how many, who sent it and which notice it is
 * when it is only one (its page, board V3-Aviso), and since when the oldest waits, as Y-m-d in the display time zone.
 */
final readonly class UnreviewedAccidents
{
    public function __construct(
        public string $workCenterId,
        public int $count,
        public ?string $personName,
        public string $since,
        public ?string $noticeId = null,
    ) {}
}
