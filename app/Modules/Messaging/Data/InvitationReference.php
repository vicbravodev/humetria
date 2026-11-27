<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * The invitation (of Campaigns, Engagement or WorkerAccess) a message belongs to, and the campaign or survey it is for. The invitation
 * already names the person and has no key to any answer, so this link adds no risk to anonymity.
 */
final readonly class InvitationReference
{
    public const string CAMPAIGN_PARTICIPANT = 'campaign_participant';

    public const string ENGAGEMENT_INVITATION = 'engagement_invitation';

    /** The portal invitation of WorkerAccess: its one-use link is both the invitation and the source. */
    public const string WORKER_ACCESS_LINK = 'worker_access_link';

    public const string CAMPAIGN = 'campaign';

    public const string ENGAGEMENT_SURVEY = 'engagement_survey';

    public const string WORKER_ACCESS = 'worker_access';

    public function __construct(
        public string $type,
        public string $id,
        public string $sourceType,
        public string $sourceId,
    ) {}
}
