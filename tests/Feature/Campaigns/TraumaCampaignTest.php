<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Actions\LaunchTraumaCampaign;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Http\Controllers\TraumaCampaignController;
use App\Modules\Campaigns\Http\Requests\TraumaCampaignRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(LaunchTraumaCampaign::class, TraumaCampaignController::class, TraumaCampaignRequest::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->people = staffOf($this->tenant, $this->plant, 5)->values();
});

function traumaForm(WorkCenter $workCenter, array $employeeIds, array $overrides = []): array
{
    return ['work_center_id' => $workCenter->id, 'employee_ids' => $employeeIds, 'closes_on' => '2026-11-09', ...$overrides];
}

it('offers the active people of the chosen work center, a page at a time', function (): void {
    $this->people[0]->update(['name' => 'Ana López', 'deactivated_at' => now()]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.trauma.create', [$this->tenant, 'work_center' => $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/trauma')
            ->where('workCenter', ['value' => $this->plant->id, 'label' => 'Planta Norte'])
            ->missing('workCenters')
            ->where('people.total', 4)
            ->has('people.data.0', fn (Assert $person) => $person->hasAll(['id', 'name', 'area', 'reachable']))
            ->where('filters', ['q' => ''])
            ->missing('matching')
            ->where('minDeadline', '2026-11-03'));

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.trauma.create', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('workCenter', null)->where('people', null));

    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.trauma.create', [$this->tenant, 'work_center' => $archived->id]))
        ->assertInertia(fn (Assert $page) => $page->where('workCenter', null)->where('people', null));
});

it('searches the people on the server, and gives everyone the search found when asked', function (): void {
    $this->people[1]->update(['name' => 'José Qwértyz']);
    $this->people[2]->update(['name' => 'Josefina Qwértyz']);
    staffOf($this->tenant, $this->plant, 25);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.trauma.create', [$this->tenant, 'work_center' => $this->plant->id, 'q' => 'qwertyz']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 2)
            ->where('filters', ['q' => 'qwertyz'])
            ->reloadOnly('matching', fn (Assert $reload) => $reload->where('matching', fn ($ids): bool => collect($ids)->sort()->values()->all() === collect([$this->people[1]->id, $this->people[2]->id])->sort()->values()->all())));

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.trauma.create', [$this->tenant, 'work_center' => $this->plant->id, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 30)->has('people.data', 5));
});

it('applies Guía I to the people chosen only, and emails them their link', function (): void {
    $chosen = [$this->people[0]->id, $this->people[2]->id];

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, $chosen))
        ->assertSessionHasNoErrors();

    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    expect($campaign->kind)->toBe(CampaignKind::Nom035Trauma)
        ->and($campaign->status)->toBe(CampaignStatus::Active)
        ->and($campaign->instruments)->toBe(['nom035.guide-i'])
        ->and($campaign->closes_on?->toDateString())->toBe('2026-11-09')
        ->and(Participant::query()->withoutGlobalScopes()->pluck('employee_id')->sort()->values()->all())->toBe(collect($chosen)->sort()->values()->all())
        ->and(ParticipantInstrument::query()->withoutGlobalScopes()->pluck('instrument')->unique()->values()->all())->toBe(['nom035.guide-i'])
        ->and(AuditEntry::query()->where('event', 'campaign.launched')->sole()->metadata)->toEqual(['participants' => 2, 'invited' => 2, 'instruments' => ['nom035.guide-i']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 2);
});

it('applies it while an evaluation of the work center is open', function (): void {
    Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, [$this->people[0]->id]))
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('refuses people who are not active in that work center, or nobody', function (Closure $people): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, $people($this)))
        ->assertSessionHasErrors('employee_ids');

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'nobody' => [fn () => []],
    'someone deactivated' => [fn ($test) => [tap($test->people[0])->update(['deactivated_at' => now()])->id]],
    'someone of another work center' => [fn ($test) => [staffOf($test->tenant, WorkCenter::factory()->for($test->tenant)->create(), 1)->first()->id]],
    'someone of another company' => [fn () => [App\Modules\Organization\Models\Employee::factory()->create()->id]],
    'not an id' => [fn () => ['abc']],
]);

it('refuses an archived work center and a deadline that is not after today', function (): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, [$this->people[0]->id], ['closes_on' => '2026-11-02']))
        ->assertSessionHasErrors('closes_on');

    $this->plant->update(['archived_at' => now()]);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, [$this->people[0]->id]))
        ->assertSessionHasErrors(['work_center_id' => 'Elige un centro de trabajo activo.']);
});

it('lets only those who manage campaigns apply it', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->get(route('tenant.campaigns.trauma.create', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.campaigns.trauma.store', $this->tenant), traumaForm($this->plant, [$this->people[0]->id]))->assertForbidden();
});
