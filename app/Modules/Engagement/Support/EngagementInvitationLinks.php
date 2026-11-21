<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Data\WhatsAppInvitation;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;

/**
 * What the WhatsApp job needs to send an invitation's survey link, read when it runs (the queue never carries it). Null when the
 * invitation no longer waits: answered, cancelled, the survey closed or past its last day, or the person left the roster.
 */
final readonly class EngagementInvitationLinks implements InvitationLinks
{
    /** The template this module's invitation is registered under in Messaging (TemplateRegistry, kept in step by its test). */
    private const string TEMPLATE = 'engagement_invite_v1';

    public function __construct(private Roster $roster, private Companies $companies) {}

    public function type(): string
    {
        return InvitationReference::ENGAGEMENT_INVITATION;
    }

    public function whatsApp(string $invitationId): ?WhatsAppInvitation
    {
        $invitation = Invitation::query()->with('survey')->find($invitationId);
        $survey = $invitation?->survey;

        if ($invitation === null || $survey === null || $invitation->answered || $invitation->cancelled) {
            return null;
        }

        if ($survey->status !== SurveyStatus::Active || $survey->closes_on === null || SurveyClock::hasPassed($survey->closes_on)) {
            return null;
        }

        $person = $this->roster->find($invitation->employee_id);

        if ($person === null || ! $person->active || $person->mobile === null) {
            return null;
        }

        $lastDay = CarbonImmutable::parse($survey->closes_on->format('Y-m-d'), config()->string('app.display_timezone'))->isoFormat('D [de] MMMM');

        return new WhatsAppInvitation(
            $person->mobile,
            self::TEMPLATE,
            [$this->companies->current()->tradeName, $lastDay],
            $invitation->surveyToken(),
        );
    }
}
