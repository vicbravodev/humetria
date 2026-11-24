<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

/**
 * The channel a person is written through, for the company in the current request: the active channel with the lowest
 * preference for which the person has data. Zero or one entry (channel => address).
 */
interface ChannelPicker
{
    /** @return array<string, string> */
    public function route(?string $email, ?string $mobile, bool $whatsAppAllowed = true): array;
}
