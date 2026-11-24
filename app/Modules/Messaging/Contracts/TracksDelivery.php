<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\InvitationReference;

/**
 * A notification that says which invitation it carries, so its delivery can be followed. Null: this one is not followed.
 */
interface TracksDelivery
{
    public function deliveryReference(): ?InvitationReference;
}
