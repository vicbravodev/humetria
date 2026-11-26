<?php

declare(strict_types=1);

use App\Modules\Announcements\Actions\SaveAnnouncement;
use App\Modules\Announcements\Contracts\PlatformNotices;
use App\Modules\Announcements\Data\AnnouncementData;
use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Enums\AnnouncementKind;
use App\Modules\Announcements\Models\Announcement;
use App\Modules\Announcements\Support\LiveNotices;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

/** Announcements written straight to the table, then kept for the panels as a save keeps them. */
function liveAnnouncement(array $attributes = [], ?string $plan = null): Announcement
{
    $announcement = ($plan === null ? Announcement::factory() : Announcement::factory()->forPlan($plan))->create($attributes);
    resolve(LiveNotices::class)->refresh();

    return $announcement;
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

it('shows the company the announcement running now, closable', function (): void {
    $announcement = liveAnnouncement(['kind' => 'incident', 'message' => 'WhatsApp no envía mensajes por ahora.']);

    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('platform_notice', [
        'id' => $announcement->id.':'.$announcement->updated_at->getTimestamp(),
        'kind' => 'incident',
        'message' => 'WhatsApp no envía mensajes por ahora.',
        'dismissible' => true,
    ]));
});

it('shows nothing for a draft, one still to come or one that ended', function (array $attributes): void {
    liveAnnouncement($attributes);

    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->missing('platform_notice'));
})->with([
    'a draft' => [['published_at' => null]],
    'one still to come' => [['starts_at' => '2026-11-26 15:00:01', 'ends_at' => '2026-11-27 00:00']],
    'one that ended' => [['starts_at' => '2026-11-25 00:00', 'ends_at' => '2026-11-26 15:00:00']],
]);

it('shows an announcement for a plan only to the companies on that plan', function (string $plan, bool $shown): void {
    Subscription::factory()->plan(Plan::from($plan))->create(['tenant_id' => $this->tenant->id]);
    liveAnnouncement(plan: 'integral');

    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $shown
        ? $page->has('platform_notice')
        : $page->missing('platform_notice'));
})->with([
    'on the plan' => ['integral', true],
    'on another plan' => ['jornada', false],
]);

it('shows the one that started last when two run at once', function (): void {
    liveAnnouncement(['message' => 'La primera', 'starts_at' => '2026-11-25 00:00']);
    liveAnnouncement(['message' => 'La última', 'starts_at' => '2026-11-26 00:00']);

    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('platform_notice.message', 'La última'));
});

it('shows a new announcement on the next page, though the panel had read none before', function (): void {
    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->missing('platform_notice'));

    resolve(SaveAnnouncement::class)->handle(new AnnouncementData(AnnouncementKind::News, AnnouncementAudience::All, null, 'Novedad', 'Ya hay reporte nuevo.', CarbonImmutable::now()->subMinute(), CarbonImmutable::now()->addDay(), true));

    $this->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('platform_notice.message', 'Ya hay reporte nuevo.'));
});

it('reads no table to tell a panel what runs now', function (): void {
    liveAnnouncement();

    $this->expectsDatabaseQueryCount(0);
    expect(resolve(PlatformNotices::class)->forCompany($this->tenant->id))->not->toBeNull();
});

it('keeps again what the panels show when the console page opens, should the cache have lost it', function (): void {
    liveAnnouncement(['message' => 'Sigue aquí']);
    Cache::flush();

    expect(resolve(PlatformNotices::class)->forCompany($this->tenant->id))->toBeNull();

    $this->actingAs(superadmin())->get(route('announcements.index'));

    expect(resolve(PlatformNotices::class)->forCompany($this->tenant->id)?->message)->toBe('Sigue aquí');
});

it('shares nothing outside a company', function (): void {
    liveAnnouncement();

    $this->actingAs(superadmin())->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->missing('platform_notice'));
});
