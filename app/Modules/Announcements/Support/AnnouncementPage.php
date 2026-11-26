<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Support;

use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Enums\AnnouncementKind;
use App\Modules\Announcements\Models\Announcement;
use App\Modules\Billing\Contracts\PlanNames;
use Carbon\CarbonImmutable;

/** What the console's «Avisos» page shows: one view of the announcements a page at a time, and the catalogs of its form. */
final readonly class AnnouncementPage
{
    private const int PER_PAGE = 20;

    public function __construct(private PlanNames $plans, private LiveNotices $live) {}

    /**
     * @return array<string, mixed>
     */
    public function props(string $view): array
    {
        // Opening the page keeps again what the panels show, should the cache have lost it.
        $this->live->refresh();
        $history = $view === 'history';
        $plans = $this->plans->all();
        $query = $history
            ? Announcement::query()->history()->orderByDesc('ends_at')
            : Announcement::query()->current()->orderBy('starts_at');

        return [
            'view' => $history ? 'history' : 'current',
            'counts' => ['current' => Announcement::query()->current()->count(), 'history' => Announcement::query()->history()->count()],
            'announcements' => $query->orderBy('id')->paginate(self::PER_PAGE)->withQueryString()->through(fn (Announcement $announcement): array => $this->row($announcement, $plans)),
            'plans' => array_map(fn (string $key, string $label): array => ['value' => $key, 'label' => $label], array_keys($plans), $plans),
            'kinds' => array_map(fn (AnnouncementKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], AnnouncementKind::cases()),
        ];
    }

    /**
     * @param  array<string, string>  $plans
     * @return array<string, mixed>
     */
    private function row(Announcement $announcement, array $plans): array
    {
        $starts = $this->local($announcement->starts_at);
        $ends = $this->local($announcement->ends_at);
        $state = $announcement->state();

        return [
            'id' => $announcement->id,
            'kind' => $announcement->kind->value,
            'kind_label' => $announcement->kind->label(),
            'audience' => $announcement->audience->value,
            'plan' => $announcement->plan,
            'audience_label' => $announcement->audience === AnnouncementAudience::All ? 'Todas las empresas' : 'Plan '.($plans[$announcement->plan ?? ''] ?? $announcement->plan),
            'title' => $announcement->title,
            'message' => $announcement->message,
            'state' => $state->value,
            'state_label' => $state->label(),
            'published' => $announcement->published_at !== null,
            'when' => $this->moment($starts).' – '.$this->moment($ends),
            'starts_on' => $starts->toDateString(),
            'starts_time' => $starts->format('H:i'),
            'ends_on' => $ends->toDateString(),
            'ends_time' => $ends->format('H:i'),
        ];
    }

    private function local(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone(config()->string('app.display_timezone'));
    }

    /** "27 nov 2026, 22:00", as the design system writes a moment. */
    private function moment(CarbonImmutable $local): string
    {
        return str_replace('.', '', $local->isoFormat('D MMM YYYY, HH:mm'));
    }
}
