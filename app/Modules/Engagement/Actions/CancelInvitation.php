<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use Illuminate\Support\Facades\Log;

/**
 * A person no longer works in the company while a survey is open: their link stops working. What they answered stays;
 * there is no way to take it back, because nothing ties it to them. A move or a new manager changes nothing: the
 * snapshot of the invitation is the group they were invited in.
 */
final class CancelInvitation
{
    /**
     * @return int how many links were cancelled.
     */
    public function handle(string $employeeId): int
    {
        $cancelled = Invitation::query()
            ->where('employee_id', $employeeId)
            ->where('answered', false)
            ->where('cancelled', false)
            ->whereHas('survey', fn ($survey) => $survey->where('status', SurveyStatus::Active))
            ->update(['cancelled' => true]);

        if ($cancelled > 0) {
            Log::info('engagement.invitation_cancelled', ['employee_id' => $employeeId, 'invitations' => $cancelled]);
        }

        return $cancelled;
    }
}
