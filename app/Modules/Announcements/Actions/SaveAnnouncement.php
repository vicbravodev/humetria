<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Actions;

use App\Actions\RecordAudit;
use App\Modules\Announcements\Data\AnnouncementData;
use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SaveAnnouncement
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Writes a new announcement, or changes one. Publishing keeps the first moment it was published; a draft is never shown.
     */
    public function handle(AnnouncementData $data, ?Announcement $announcement = null): Announcement
    {
        return DB::transaction(function () use ($data, $announcement): Announcement {
            $event = $announcement === null ? 'announcement.created' : 'announcement.updated';
            $announcement ??= new Announcement;
            $plan = $data->audience === AnnouncementAudience::Plan ? $data->plan : null;

            $announcement->fill([
                'kind' => $data->kind,
                'audience' => $data->audience,
                'plan' => $plan,
                'title' => $data->title,
                'message' => $data->message,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'published_at' => $data->publish ? ($announcement->published_at ?? now()) : null,
            ])->save();

            Log::info($event, ['announcement_id' => $announcement->id]);
            $this->recordAudit->handle($event, $announcement, ['kind' => $data->kind->value, 'plan' => $plan]);

            return $announcement;
        });
    }
}
