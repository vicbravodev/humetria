<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

use App\Modules\Campaigns\Enums\Standing;

/**
 * Which people of a campaign to list: where they stand (null: everyone), with one guide or all of them, of an area,
 * and with text in their name, email or number.
 */
final readonly class PeopleFilter
{
    public function __construct(
        public ?Standing $standing = Standing::Pending,
        public ?string $guide = null,
        public ?string $areaId = null,
        public string $search = '',
    ) {}
}
