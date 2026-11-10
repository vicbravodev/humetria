<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Listeners;

use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When an evaluation closes, the system drafts its Programa de intervención (num. 8.3) so whoever closes it finds it ready.
 * A campaign of only the Guía I has none, and a blocked company gets nothing.
 */
final readonly class DraftProgramOnClose implements ShouldQueue
{
    public function __construct(private Companies $companies, private CreateProgram $create) {}

    public function handle(CampaignClosed $event): void
    {
        if ($this->companies->enter($event->tenantId)->stopped('nom035')) {
            return;
        }

        try {
            $this->create->handle($event->campaignId, null);
        } catch (ProgramUnavailable) {
            // A Guía I campaign (or one that vanished) has no Programa to draft.
        }
    }
}
