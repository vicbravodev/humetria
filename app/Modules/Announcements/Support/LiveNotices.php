<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Support;

use App\Modules\Announcements\Contracts\PlatformNotices;
use App\Modules\Announcements\Data\CompanyNotice;
use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Models\Announcement;
use App\Modules\Billing\Contracts\PlanNames;
use Illuminate\Support\Facades\Cache;

/**
 * Every published announcement that has not ended, kept in the cache and filtered
 * by the clock on each read, so a page of the panel reads no table for it, not even the first one. Every save writes it again, and so does opening the console's page.
 * ponytail: with the cache cleared by hand the panels show nothing until the next save or visit to «Avisos»; nothing clears it today.
 */
final readonly class LiveNotices implements PlatformNotices
{
    private const string KEY = 'announcements.live';

    public function __construct(private PlanNames $plans) {}

    /** Reads the table again and keeps what the panels will show. */
    public function refresh(): void
    {
        Cache::forever(self::KEY, Announcement::query()
            ->whereNotNull('published_at')
            ->where('ends_at', '>', now())
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Announcement $announcement): LiveAnnouncement => new LiveAnnouncement(
                id: $announcement->id.':'.$announcement->updated_at->getTimestamp(),
                kind: $announcement->kind->value,
                plan: $announcement->audience === AnnouncementAudience::Plan ? $announcement->plan : null,
                message: $announcement->message,
                startsAt: $announcement->starts_at->getTimestamp(),
                endsAt: $announcement->ends_at->getTimestamp(),
            ))
            ->all());
    }

    public function forCompany(string $tenantId): ?CompanyNotice
    {
        $now = now()->getTimestamp();
        // The company's plan is read only when an announcement for one plan is running, and once.
        $planOf = null;

        foreach ($this->snapshot() as $announcement) {
            if (! $announcement->runsAt($now)) {
                continue;
            }

            if ($announcement->plan !== null && $announcement->plan !== ($planOf ??= [$this->plans->keyOf($tenantId)])[0]) {
                continue;
            }

            return new CompanyNotice($announcement->id, $announcement->kind, $announcement->message, true);
        }

        return null;
    }

    /**
     * @return list<LiveAnnouncement>
     */
    private function snapshot(): array
    {
        $snapshot = Cache::get(self::KEY);

        return is_array($snapshot) ? array_values(array_filter($snapshot, fn (mixed $item): bool => $item instanceof LiveAnnouncement)) : [];
    }
}
