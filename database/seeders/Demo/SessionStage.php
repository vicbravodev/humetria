<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Tenancy\Actions\CreateWorkCenter;
use App\Modules\Tenancy\Actions\SetGroupMinimum;
use App\Modules\Tenancy\Actions\UpdateCompanyChannels;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Data\ChannelSettingsData;
use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * The room of a guided session, staged after the story: a work center of its own for the audience, the group minimum at 1
 * (a handful of people sees its results) and WhatsApp on with the words in force, as the owner would leave them in «Canales
 * de comunicación». Each through the action the panel uses.
 */
final readonly class SessionStage
{
    public function __construct(
        private Companies $companies,
        private CreateWorkCenter $createWorkCenter,
        private SetGroupMinimum $setGroupMinimum,
        private UpdateCompanyChannels $updateChannels,
    ) {}

    /** @return string the id of the session's work center */
    public function stage(Tenant $demo, ?string $clientName): string
    {
        $this->companies->enter($demo->id);

        // At the offices' address: the session is shown from there.
        $center = $this->createWorkCenter->handle(new WorkCenterData(
            $clientName === null ? 'Sala de demostración' : "Sala · {$clientName}",
            'Boulevard Bernardo Quintana', '4100', 'Piso 2', 'Álamos 3a Sección', '76160', 'Querétaro', MexicanState::Queretaro,
            'Servicios administrativos y comerciales',
        ));
        $this->setGroupMinimum->handle($demo, GroupMinimums::LOWEST);
        $this->updateChannels->handle($demo->id, new ChannelSettingsData(
            mailEnabled: true,
            whatsAppEnabled: true,
            preferred: 'mail',
            whatsAppMonthlyCap: null,
            consentAccepted: true,
        ), null);

        Log::info('demo.session_staged', ['tenant_id' => $demo->id, 'work_center_id' => $center->id]);

        return $center->id;
    }
}
