<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\Questionnaire;

/**
 * A survey link that works: the invitation, its survey and company, and the name of the manager the person was asked
 * about (null when they had none, or he can no longer be found).
 */
final readonly class OpenedInvitation
{
    public function __construct(public Invitation $invitation, public Survey $survey, public string $company, public ?string $managerName) {}

    /** @return list<BankItem> the questions this person is shown */
    public function presented(): array
    {
        return Questionnaire::presentedTo($this->survey->items, $this->managerName !== null);
    }
}
