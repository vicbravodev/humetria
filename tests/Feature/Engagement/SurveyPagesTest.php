<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Http\Controllers\SurveyController;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SurveyController::class, SurveyPages::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-09 18:00:00');
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
});

it('lives under the climate of the company', function (): void {
    expect(route('tenant.climate.index', $this->tenant, absolute: false))->toBe('/admin/acme/climate');
});

it('opens on an empty state with the next step when there is no survey yet', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('engagement/index')
        ->where('current', null)
        ->where('canManage', true)
        ->where('surveys.total', 0));
});

it('shows the survey in progress and the closed ones, paged', function (): void {
    $current = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id, 'name' => 'Clima · octubre 2026']);
    Survey::factory()->closed()->count(11)->create(['tenant_id' => $this->tenant->id, 'kind' => SurveyKind::Pulse]);
    $old = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'name' => 'Clima · 2025']);

    $props = $this->actingAs($this->viewer)->get(route('tenant.climate.index', $this->tenant))->assertOk()->inertiaProps();

    expect($props['current'])->toMatchArray(['id' => $current->id, 'name' => 'Clima · octubre 2026', 'status' => 'active', 'statusLabel' => 'En curso'])
        ->and($props['canManage'])->toBeFalse()
        ->and($props['surveys']['total'])->toBe(1)
        ->and($props['surveys']['data'][0])->toMatchArray(['id' => $old->id, 'name' => 'Clima · 2025']);
});

it('pages the history of closed surveys', function (): void {
    Survey::factory()->closed()->count(12)->create(['tenant_id' => $this->tenant->id]);

    $props = $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->inertiaProps();

    expect($props['surveys']['total'])->toBe(12)->and($props['surveys']['data'])->toHaveCount(SurveyPages::PER_PAGE);
});

it('lets owner and hr start a draft and sends them to it', function (TenantRole $role): void {
    $survey = null;
    $this->actingAs(memberOf($this->tenant, $role))->post(route('tenant.climate.store', $this->tenant))->assertRedirect();

    $survey = Survey::query()->sole();

    expect($survey->status)->toBe(SurveyStatus::Draft)->and($survey->kind)->toBe(SurveyKind::Climate);
    $this->get(route('tenant.climate.show', [$this->tenant, $survey]))->assertOk();
})->with([TenantRole::Owner, TenantRole::Hr]);

it('keeps a viewer from creating, deleting, launching, reminding or closing', function (): void {
    $draft = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->viewer);

    $this->post(route('tenant.climate.store', $this->tenant))->assertForbidden();
    $this->delete(route('tenant.climate.destroy', [$this->tenant, $draft]))->assertForbidden();
    $this->post(route('tenant.climate.launch', [$this->tenant, $draft]), ['closes_on' => '2026-11-20'])->assertForbidden();
    $this->post(route('tenant.climate.launch', [$this->tenant, $draft]))->assertForbidden();

    $draft->delete();
    $active = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $this->post(route('tenant.climate.remind', [$this->tenant, $active]))->assertForbidden();
    $this->post(route('tenant.climate.close', [$this->tenant, $active]))->assertForbidden();

    expect(Survey::query()->sole()->status)->toBe(SurveyStatus::Active);
});

it('tells a second draft apart instead of failing', function (): void {
    Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr)->post(route('tenant.climate.store', $this->tenant))->assertRedirect(route('tenant.climate.index', $this->tenant));

    expect(Survey::query()->count())->toBe(1);
});

it('throws a draft away and refuses to delete a launched survey', function (): void {
    $draft = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->hr);

    $this->delete(route('tenant.climate.destroy', [$this->tenant, $draft]))->assertRedirect(route('tenant.climate.index', $this->tenant));
    expect(Survey::query()->count())->toBe(0);

    $active = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $this->delete(route('tenant.climate.destroy', [$this->tenant, $active]))->assertStatus(409);
    expect(Survey::query()->count())->toBe(1);
});

it('answers 404 for a survey of another company and for one of another kind', function (): void {
    $other = Tenant::factory()->create();
    $theirs = Survey::factory()->create(['tenant_id' => $other->id]);
    $pulse = Survey::factory()->pulse()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->hr);

    $this->get(route('tenant.climate.show', [$this->tenant, $theirs->id]))->assertNotFound();
    $this->delete(route('tenant.climate.destroy', [$this->tenant, $theirs->id]))->assertNotFound();
    $this->post(route('tenant.climate.launch', [$this->tenant, $theirs->id]), ['closes_on' => '2026-11-20'])->assertNotFound();
    $this->post(route('tenant.climate.remind', [$this->tenant, $theirs->id]))->assertNotFound();
    $this->post(route('tenant.climate.close', [$this->tenant, $theirs->id]))->assertNotFound();
    $this->get(route('tenant.climate.show', [$this->tenant, $pulse->id]))->assertNotFound();

    expect(Survey::query()->withoutGlobalScopes()->whereKey($theirs->id)->exists())->toBeTrue();
});
