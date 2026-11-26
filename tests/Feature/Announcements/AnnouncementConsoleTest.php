<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Announcements\Models\Announcement;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // 9:00 in Mexico City.
    $this->travelTo('2026-11-26 15:00:00');
});

/** @return array<string, mixed> a valid announcement as the form sends it, times in Mexico City */
function announcementForm(array $overrides = []): array
{
    return [
        'kind' => 'maintenance',
        'audience' => 'all',
        'plan' => null,
        'title' => 'Mantenimiento programado',
        'message' => 'Esta noche haremos mantenimiento de 10:00 p. m. a 1:00 a. m.',
        'starts_on' => '2026-11-28',
        'starts_time' => '22:00',
        'ends_on' => '2026-11-29',
        'ends_time' => '01:00',
        'publish' => true,
        ...$overrides,
    ];
}

it('lives under /admin/announcements and sends guests to the login', function (): void {
    expect(route('announcements.index', absolute: false))->toBe('/admin/announcements');

    $this->get(route('announcements.index'))->assertRedirect(route('login'));
});

it('answers 404 to support and to everyone who is not the superadmin, before validating', function (Closure $who): void {
    $user = $who();

    $this->actingAs($user)->get(route('announcements.index'))->assertNotFound();
    $this->actingAs($user)->post(route('announcements.store'), [])->assertNotFound()->assertSessionHasNoErrors();
    $this->actingAs($user)->put(route('announcements.update', Announcement::factory()->create()), [])->assertNotFound()->assertSessionHasNoErrors();
})->with([
    'support' => [fn (): User => support()],
    'a user' => [fn (): User => User::factory()->create()],
]);

it('splits the announcements into the ones to come or running and the history, a page at a time', function (): void {
    Announcement::factory()->create(['title' => 'Borrador', 'published_at' => null, 'starts_at' => '2026-12-01 10:00', 'ends_at' => '2026-12-02 10:00']);
    Announcement::factory()->create(['title' => 'Programado', 'starts_at' => '2026-11-28 04:00', 'ends_at' => '2026-11-29 07:00']);
    Announcement::factory()->create(['title' => 'Activo', 'kind' => 'news', 'starts_at' => '2026-11-25 10:00', 'ends_at' => '2026-12-02 10:00']);
    Announcement::factory()->create(['title' => 'Terminado', 'starts_at' => '2026-11-19 10:00', 'ends_at' => '2026-11-19 12:00']);

    $this->actingAs(superadmin())->get(route('announcements.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('announcements/index')
        ->where('view', 'current')
        ->where('counts', ['current' => 3, 'history' => 1])
        ->has('announcements.data', 3)
        ->where('announcements.data.0.title', 'Activo')
        ->where('announcements.data.0.state', 'active')
        ->where('announcements.data.0.state_label', 'Activo')
        ->where('announcements.data.0.kind_label', 'Novedad')
        ->where('announcements.data.0.audience_label', 'Todas las empresas')
        ->where('announcements.data.1.title', 'Programado')
        ->where('announcements.data.1.state', 'scheduled')
        ->where('announcements.data.1.starts_on', '2026-11-27')
        ->where('announcements.data.1.starts_time', '22:00')
        ->where('announcements.data.1.when', '27 nov 2026, 22:00 – 29 nov 2026, 01:00')
        ->where('announcements.data.2.state', 'draft')
        ->where('plans', fn ($plans) => collect($plans)->pluck('label')->contains('Integral'))
        ->has('kinds', 4));

    $this->get(route('announcements.index', ['view' => 'history']))->assertInertia(fn (Assert $page) => $page
        ->where('view', 'history')
        ->has('announcements.data', 1)
        ->where('announcements.data.0.title', 'Terminado')
        ->where('announcements.data.0.state', 'ended'));
});

it('schedules an announcement, with its times read in Mexico City and kept in UTC, and leaves it in the trail', function (): void {
    Log::spy();
    $this->actingAs(superadmin())->post(route('announcements.store'), announcementForm())->assertRedirect()->assertSessionHasNoErrors();

    $announcement = Announcement::query()->sole();
    expect($announcement->kind->value)->toBe('maintenance')
        ->and($announcement->starts_at->toIso8601String())->toBe('2026-11-29T04:00:00+00:00')
        ->and($announcement->ends_at->toIso8601String())->toBe('2026-11-29T07:00:00+00:00')
        ->and($announcement->published_at?->toIso8601String())->toBe('2026-11-26T15:00:00+00:00')
        ->and(AuditEntry::query()->where('event', 'announcement.created')->sole()->metadata)->toBe(['kind' => 'maintenance', 'plan' => null]);
    Log::shouldHaveReceived('info')->with('announcement.created', ['announcement_id' => $announcement->id]);
});

it('keeps a draft unpublished', function (): void {
    $this->actingAs(superadmin())->post(route('announcements.store'), announcementForm(['publish' => false]))->assertSessionHasNoErrors();

    expect(Announcement::query()->sole()->published_at)->toBeNull();
});

it('targets one plan of the catalog', function (): void {
    $this->actingAs(superadmin())->post(route('announcements.store'), announcementForm(['audience' => 'plan', 'plan' => 'integral']))->assertSessionHasNoErrors();

    expect(Announcement::query()->sole()->plan)->toBe('integral');

    $this->get(route('announcements.index'))->assertInertia(fn (Assert $page) => $page->where('announcements.data.0.audience_label', 'Plan Integral'));
});

it('edits an announcement and leaves it in the trail', function (): void {
    $announcement = Announcement::factory()->create(['published_at' => null]);

    $this->actingAs(superadmin())->put(route('announcements.update', $announcement), announcementForm(['title' => 'Otro título']))->assertSessionHasNoErrors();

    expect($announcement->refresh()->title)->toBe('Otro título')
        ->and($announcement->published_at)->not->toBeNull()
        ->and(AuditEntry::query()->where('event', 'announcement.updated')->count())->toBe(1);
});

it('rejects what is not a valid announcement', function (array $overrides, string $field): void {
    $this->actingAs(superadmin())->post(route('announcements.store'), announcementForm($overrides))->assertSessionHasErrors($field);

    expect(Announcement::query()->count())->toBe(0);
})->with([
    'an unknown kind' => [['kind' => 'promo'], 'kind'],
    'a plan audience without a plan' => [['audience' => 'plan', 'plan' => null], 'plan'],
    'a plan not in the catalog' => [['audience' => 'plan', 'plan' => 'gratis'], 'plan'],
    'no message' => [['message' => ''], 'message'],
    'a message too long' => [['message' => str_repeat('a', 501)], 'message'],
    'an end before the start' => [['ends_on' => '2026-11-28', 'ends_time' => '21:59'], 'ends_on'],
    'an end equal to the start' => [['ends_on' => '2026-11-28', 'ends_time' => '22:00'], 'ends_on'],
    'a time that is not HH:mm' => [['starts_time' => '25:00'], 'starts_time'],
]);

it('drops the plan when the announcement goes to every company', function (): void {
    $this->actingAs(superadmin())->post(route('announcements.store'), announcementForm(['audience' => 'all', 'plan' => 'integral']))->assertSessionHasNoErrors();

    expect(Announcement::query()->sole()->plan)->toBeNull();
});
