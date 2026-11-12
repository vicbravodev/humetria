<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Enums\SurveyUnavailableReason;
use App\Modules\Engagement\Exceptions\SurveyUnavailable;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Support\OpenedInvitation;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Opens the survey of the person whose link carries `$token`, inside their company. The link works while the survey is
 * active and before its last day ends, the company may be used, the link was not cancelled and was not used.
 */
final readonly class OpenInvitation
{
    public function __construct(private Companies $companies, private Roster $roster) {}

    /**
     * @throws SurveyUnavailable
     */
    public function handle(string $token, bool $forUpdate = false): OpenedInvitation
    {
        // Read past the company scope: until the link is known, no company is.
        $invitation = Invitation::query()->withoutGlobalScopes()->where('token_hash', Invitation::hashToken($token))->first();

        if ($invitation === null || $invitation->cancelled) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Invalid);
        }

        $company = $this->companies->enter($invitation->tenant_id);
        // Sending an answer holds the survey with a shared lock, so closing it waits until the answer is in, and then
        // the invitation, so two answers of the same link wait on each other. Survey first, always.
        $survey = ($forUpdate ? $invitation->survey()->sharedLock() : $invitation->survey())->firstOrFail();

        if ($forUpdate) {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);
        }

        if ($company->stopped($survey->kind->value)) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Suspended, $company->tradeName);
        }

        if ($survey->status !== SurveyStatus::Active || $survey->closes_on === null || SurveyClock::hasPassed($survey->closes_on)) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Closed, $company->tradeName);
        }

        // Cancelled while we waited for the lock: the link no longer exists.
        if ($invitation->cancelled) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Invalid);
        }

        if ($invitation->answered) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Done, $company->tradeName);
        }

        // The person answers about the manager they had when invited, even if he has left since.
        $manager = $invitation->about_manager_id === null ? null : $this->roster->find($invitation->about_manager_id);

        return new OpenedInvitation($invitation, $survey, $company->tradeName, $manager?->name);
    }
}
