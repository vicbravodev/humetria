<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Contracts\ComplaintsToAcknowledge;
use Carbon\CarbonImmutable;

/**
 * Quejas: whether the channel takes cases and, only to whoever handles them, how many of theirs wait for acknowledgment and
 * when the first falls due. Never what a complaint says, who presented it or whom it is about; to anyone else, not even
 * how many there are.
 */
final readonly class ComplaintsReader implements ComplianceReader
{
    public function __construct(private ComplaintChannels $channels, private ComplaintsToAcknowledge $complaints, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        $open = $this->channels->current()->available;

        if (! $scope->canSee('handle-complaints')) {
            return $open
                ? new ComplianceStatus('complaints', 'Quejas', 'up_to_date', 'Al día', 'Tu canal de quejas recibe casos; cuántos hay solo lo ven quienes los atienden.', $this->links->dashboard())
                : new ComplianceStatus('complaints', 'Quejas', 'pending', 'Pendiente', 'Tu canal de quejas aún no recibe casos.', $this->links->dashboard());
        }

        $waiting = $this->complaints->count($scope->userId);
        $first = $this->complaints->soonest($scope->userId, 1)[0] ?? null;

        return match (true) {
            $first !== null => new ComplianceStatus('complaints', 'Quejas', 'pending', 'Pendiente', 'Tienes '.trans_choice('{1} :count caso|[2,*] :count casos', $waiting).' por acusar de recibo; el primero vence el '.CarbonImmutable::parse($first->dueOn)->isoFormat('D [de] MMMM').'.', $this->links->complaints()),
            ! $open => new ComplianceStatus('complaints', 'Quejas', 'pending', 'Pendiente', 'Tu canal de quejas aún no recibe casos.', $this->links->complaints()),
            default => new ComplianceStatus('complaints', 'Quejas', 'up_to_date', 'Al día', 'No tienes casos por acusar de recibo.', $this->links->complaints()),
        };
    }
}
