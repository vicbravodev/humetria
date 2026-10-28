<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

final readonly class OwnerInvitationData
{
    /**
     * @param  string  $email  Lowercase, without spaces.
     */
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
