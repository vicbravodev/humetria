<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\ChannelDeliveryTally;

/**
 * How the messages of a campaign or survey went, by channel, for the company in the current request: only aggregates, with the
 * minimum of rule 10 applied. It counts messages (an invitation, a reminder, a resend and a fallback are one each), not people, and
 * leaves out a channel the company has turned off.
 */
interface DeliverySummary
{
    /**
     * @param  string  $sourceType  an InvitationReference source constant (campaign, engagement_survey)
     * @return list<ChannelDeliveryTally> mail first, then WhatsApp; a channel with no messages is not there.
     */
    public function forSource(string $sourceType, string $sourceId): array;
}
