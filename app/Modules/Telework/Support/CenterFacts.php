<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\TeleworkDocument;

/**
 * What the evidence of one work center amounts to, counted: the input of the 17 rows of table 10.3. `ComplianceRows`
 * reads it for many centers at once and builds one of these per center.
 */
final readonly class CenterFacts
{
    /**
     * @param  array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int}  $places
     * @param  array<string, TeleworkDocument>  $documents  in force, by kind
     * @param  array<int, list<Requirement>>  $uncovered  by section; empty when there is no published list
     * @param  array<string, array{acknowledged: int, expected: int}>  $acks  by document id
     */
    public function __construct(
        public int $active,
        public int $aspirants,
        public array $places,
        public array $documents,
        public ?Checklist $checklist,
        public bool $sectionTwo,
        public array $uncovered,
        public array $acks,
        public int $equipmentCovered,
        public int $equipmentUnconfirmed,
        public int $overdue,
        public int $dueSoon,
        public int $noticeTotal,
        public int $noticeReviewed,
        public bool $committee,
        public int $members,
    ) {}
}
