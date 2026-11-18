<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\ChannelSetting;

/**
 * The channels a company talks through. Without a row, mail is on and first and WhatsApp is off and second, so a company that
 * existed before keeps its mail. It serves any company by id, not only the current one, for jobs and webhooks.
 */
interface CompanyChannels
{
    /**
     * Always both channels, ordered by preference and, on a tie, mail first.
     *
     * @return list<ChannelSetting>
     */
    public function settings(string $tenantId): array;

    /** The owner's monthly cap of messages on a channel, or null when there is none. */
    public function monthlyCap(string $tenantId, string $channel): ?int;
}
