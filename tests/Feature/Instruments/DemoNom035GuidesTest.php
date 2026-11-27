<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\DemoNom035Guides;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DemoNom035Guides::class);

/*
 * The demo session's work center is no company: a handful of people in the room sees Guía II and its results (spec
 * 2026-11-26-demo-guiada §2.2). Everywhere else, the demo's other centers included, the norm's rule stands.
 */

/** A demo session, ready, in `$center`. */
function sessionIn(WorkCenter $center): void
{
    DemoSession::start(null, ['nom035'])?->markReady($center->id);
}

beforeEach(function (): void {
    $this->demo = Tenant::factory()->demo()->create();
    $this->room = WorkCenter::factory()->for($this->demo)->create(['name' => 'Sala de demostración']);
});

it('applies guides I and II to 3 workers in the demo', function (): void {
    sessionIn($this->room);
    workIn($this->demo);

    $guides = resolve(Nom035Guides::class);

    expect($guides->for(3, $this->room->id))->toBe(['I', 'II'])
        ->and($guides->for(15, $this->room->id))->toBe(['I', 'II'])
        ->and($guides->instruments(3, $this->room->id))->toBe(['nom035.guide-i', 'nom035.guide-ii'])
        ->and($guides->for(0, $this->room->id))->toBe([])
        ->and($guides->for(3))->toBe(['I']);
});

it('keeps guide I only for 3 workers in a real company', function (): void {
    $company = Tenant::factory()->create();
    $office = WorkCenter::factory()->for($company)->create();
    sessionIn($this->room);
    workIn($company);

    $guides = resolve(Nom035Guides::class);

    expect($guides->for(3, $office->id))->toBe(['I'])
        ->and($guides->instruments(3, $office->id))->toBe(['nom035.guide-i'])
        ->and($guides->for(3, $this->room->id))->toBe(['I']);
});

it('keeps guide I only when the demo has no session', function (): void {
    workIn($this->demo);

    expect(resolve(Nom035Guides::class)->for(3, $this->room->id))->toBe(['I']);
});

it('launches a demo evaluation of 3 people with guide II', function (): void {
    Notification::fake();
    sessionIn($this->room);
    staffOf($this->demo, $this->room, 3);
    $hr = memberOf($this->demo, TenantRole::Hr);

    $this->actingAs($hr)->post(route('tenant.campaigns.store', $this->demo), ['work_center_id' => $this->room->id, 'population' => 'all'])
        ->assertSessionHasNoErrors();
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    $this->actingAs($hr)->post(route('tenant.campaigns.launch', [$this->demo, $campaign->id]), ['closes_on' => now()->addWeeks(2)->toDateString()])
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->instruments)->toBe(['nom035.guide-i', 'nom035.guide-ii'])
        ->and($campaign->status)->toBe(CampaignStatus::Active);
});

it('keeps I and III over 50 in the demo', function (): void {
    sessionIn($this->room);
    workIn($this->demo);

    $guides = resolve(Nom035Guides::class);

    expect($guides->for(51, $this->room->id))->toBe(['I', 'III'])
        ->and($guides->for(50, $this->room->id))->toBe(['I', 'II'])
        ->and($guides->for(16, $this->room->id))->toBe(['I', 'II']);
});

it('keeps guide I only in Sucursal Monterrey during a session', function (): void {
    Notification::fake();
    $monterrey = WorkCenter::factory()->for($this->demo)->create(['name' => 'Sucursal Monterrey']);
    staffOf($this->demo, $monterrey, 15);
    sessionIn($this->room);
    workIn($this->demo);

    expect(resolve(Nom035Guides::class)->for(15, $monterrey->id))->toBe(['I']);

    $this->actingAs(memberOf($this->demo, TenantRole::Hr))->post(route('tenant.campaigns.store', $this->demo), ['work_center_id' => $monterrey->id, 'population' => 'all'])
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->withoutGlobalScopes()->sole()->instruments)->toBe(['nom035.guide-i']);
});

it('shows guides I and II for the session center on every page', function (): void {
    sessionIn($this->room);
    staffOf($this->demo, $this->room, 3);
    $this->actingAs(memberOf($this->demo, TenantRole::Hr));

    $this->get(route('tenant.campaigns.index', $this->demo))
        ->assertInertia(fn (Assert $page) => $page->where('workCenters.data.0.guides', ['I', 'II'])->where('centersBySize', ['small' => 0, 'medium' => 1, 'large' => 0]));
    $this->get(route('tenant.work-centers.index', $this->demo))
        ->assertInertia(fn (Assert $page) => $page->where('workCenters.data.0.guides', ['I', 'II']));
    $this->get(route('tenant.work-centers.applies', [$this->demo, $this->room->id]))
        ->assertInertia(fn (Assert $page) => $page->where('range', '16_to_50'));
    $this->get(route('tenant.work-centers.onboarding', $this->demo))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.range', '16_to_50'));
    $this->get(route('tenant.nom035.route', [$this->demo, $this->room->id]))
        ->assertInertia(fn (Assert $page) => $page->where('rows', fn ($rows): bool => collect($rows)->doesntContain('state', 'not_applicable')));
});
