<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

/** Who a document is for today, worked out once: employee ids and Comisión user ids. */
final readonly class AckAudienceIds
{
    /**
     * @param  list<string>  $employeeIds
     * @param  list<int>  $userIds
     */
    public function __construct(public array $employeeIds, public array $userIds) {}

    public function expected(): int
    {
        return count($this->employeeIds) + count($this->userIds);
    }
}
