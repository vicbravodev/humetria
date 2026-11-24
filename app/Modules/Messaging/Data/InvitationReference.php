<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * The invitation (of Campaigns or Engagement) a message belongs to, and the campaign or survey it is for. The invitation
 * already names the person and has no key to any answer, so this link adds no risk to anonymity.
 */
final readonly class InvitationReference
{
    public const string CAMPAIGN_PARTICIPANT = 'campaign_participant';

    public const string ENGAGEMENT_INVITATION = 'engagement_invitation';

    public const string CAMPAIGN = 'campaign';

    public const string ENGAGEMENT_SURVEY = 'engagement_survey';

    public function __construct(
        public string $type,
        public string $id,
        public string $sourceType,
        public string $sourceId,
    ) {}
}
