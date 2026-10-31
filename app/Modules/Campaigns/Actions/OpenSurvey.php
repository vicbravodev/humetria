<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\SurveyUnavailableReason;
use App\Modules\Campaigns\Exceptions\SurveyUnavailable;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Campaigns\Support\OpenedSurvey;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Opens the survey of the person whose link carries `$token`, inside their company. The link works while the campaign
 * is active and before its last day ends, the company may be used, and the person has a guide left to answer.
 */
final readonly class OpenSurvey
{
    public function __construct(private Companies $companies) {}

    /**
     * @throws SurveyUnavailable
     */
    public function handle(string $token, bool $forUpdate = false): OpenedSurvey
    {
        // Read past the company scope: until the link is known, no company is.
        $participant = Participant::query()->withoutGlobalScopes()->where('token_hash', Participant::hashToken($token))->first();

        if ($participant === null) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Invalid);
        }

        $company = $this->companies->enter($participant->tenant_id);
        // Sending an answer holds the campaign with a shared lock, so closing it waits until the answer is in, and then
        // the person, so two answers of the same guide wait on each other. Campaign first: the order every writer of a
        // campaign takes its locks in, so none deadlocks with another.
        $campaign = ($forUpdate ? $participant->campaign()->sharedLock() : $participant->campaign())->firstOrFail();

        if ($forUpdate) {
            $participant = Participant::query()->lockForUpdate()->findOrFail($participant->id);
        }

        if ($company->blocked) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Suspended, $company->tradeName);
        }

        if ($campaign->status !== CampaignStatus::Active || $campaign->closes_on === null || CampaignClock::hasPassed($campaign->closes_on)) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Closed, $company->tradeName);
        }

        if (! $participant->instruments()->where('status', InstrumentStatus::Pending)->exists()) {
            throw new SurveyUnavailable(SurveyUnavailableReason::Done, $company->tradeName);
        }

        return new OpenedSurvey($participant, $campaign, $company->tradeName);
    }
}
