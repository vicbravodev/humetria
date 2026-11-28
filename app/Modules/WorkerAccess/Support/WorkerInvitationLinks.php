<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Data\WhatsAppInvitation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;

/**
 * What the WhatsApp job needs to send a portal invitation, read when it runs (the queue never carries it): the person's mobile,
 * the company's name and the link's token for the button (`access/{token}`). Null when the link no longer opens or the person
 * left the roster.
 */
final readonly class WorkerInvitationLinks implements InvitationLinks
{
    /** The template this invitation is registered under in Messaging (TemplateRegistry, kept in step by its test). */
    private const string TEMPLATE = 'worker_invite_v1';

    public function __construct(private Roster $roster, private Companies $companies) {}

    public function type(): string
    {
        return InvitationReference::WORKER_ACCESS_LINK;
    }

    public function whatsApp(string $invitationId): ?WhatsAppInvitation
    {
        $link = WorkerAccessLink::findPendingById($invitationId);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        if ($link === null || $person === null || ! $person->active || $person->mobile === null) {
            return null;
        }

        return new WhatsAppInvitation($person->mobile, self::TEMPLATE, [$this->companies->current()->tradeName], $link->token());
    }
}
