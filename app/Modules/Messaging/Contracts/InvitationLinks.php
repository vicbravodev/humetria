<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\WhatsAppInvitation;

/**
 * What an invitation module (Campaigns, Engagement) tells Messaging at the moment of sending: the phone, the template, its two
 * parameters and the link token. The job carries only the delivery id, so the token and the phone are read here and never travel
 * through the queue. Each implementation is tagged with TAG, like `worker.sections`.
 */
interface InvitationLinks
{
    public const string TAG = 'messaging.invitation-links';

    /** The invitation type it answers for (an InvitationReference constant). */
    public function type(): string;

    /** Null when the invitation no longer waits: answered, cancelled, closed, or the person left the roster. */
    public function whatsApp(string $invitationId): ?WhatsAppInvitation;
}
