<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use Carbon\CarbonImmutable;

final readonly class TenantHumiActivity implements HumiActivity
{
    public function hasAsked(int $userId): bool
    {
        // Both models are scoped to the current company; the participant is a subquery, so it is one query.
        return AiUsage::query()
            ->whereIn('participant_id', ConversationParticipant::query()->where('user_id', $userId)->select('id'))
            ->exists();
    }

    public function askedSince(CarbonImmutable $since): bool
    {
        // Humi's use has a participant; another module's record() has none.
        return AiUsage::query()->whereNotNull('participant_id')->where('created_at', '>=', $since)->exists();
    }
}
