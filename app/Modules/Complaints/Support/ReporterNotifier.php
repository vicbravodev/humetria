<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Notifications\NewsOnYourCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells the person, at the address they left for it, that their case has news: at most once a day, and only that. The notice
 * goes at once (not through the queue, which would hold the address) and never fails the handler's action: a mail that cannot
 * go is reported without the address and the case carries on.
 */
final readonly class ReporterNotifier
{
    public function news(Complaint $complaint): void
    {
        $email = $complaint->reporter_email;

        if ($email === null || ! Cache::add("complaints:news:{$complaint->id}:".Dates::today()->toDateString(), true, now()->addDay())) {
            return;
        }

        $channel = ComplaintChannel::query()->findOrFail($complaint->channel_id);

        try {
            Notification::route('mail', $email)->notifyNow(new NewsOnYourCase(route('complaints.follow-up.show', $channel->access_code)));
        } catch (Throwable $failure) {
            report($failure);
        }
    }
}
