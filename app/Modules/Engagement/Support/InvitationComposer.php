<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use LogicException;

/**
 * Builds the notification that carries a person's survey link: the one place that knows what an invitation is made of, used by
 * the job that sends them and by the fallback to mail. What is the same for everyone in the company is read once per instance.
 */
final class InvitationComposer
{
    private ?CompanySummary $company = null;

    private ?string $contact = null;

    private ?string $home = null;

    public function __construct(private readonly Companies $companies, private readonly WorkerLinks $links) {}

    public function compose(Invitation $invitation, Survey $survey, RosterEntry $person, string $token, bool $reminder): SurveyInvitation
    {
        $this->company ??= $this->companies->current();
        $this->contact ??= $this->companies->contact();
        $this->home ??= $this->links->home();

        return new SurveyInvitation(
            $reminder,
            $token,
            $person->name,
            $this->company,
            $this->contact,
            $survey->closes_on ?? throw new LogicException("Survey [{$survey->id}] has no last day."),
            $survey->kind,
            $this->home,
            new InvitationReference(InvitationReference::ENGAGEMENT_INVITATION, $invitation->id, InvitationReference::ENGAGEMENT_SURVEY, $survey->id),
        );
    }
}
