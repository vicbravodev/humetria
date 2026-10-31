<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CreateEvaluationDraft;
use App\Modules\Campaigns\Actions\DeleteDraft;
use App\Modules\Campaigns\Actions\DrawSample;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Http\Controllers\CampaignController;
use App\Modules\Campaigns\Http\Requests\CreateEvaluationRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

mutates(CreateEvaluationDraft::class, DrawSample::class, DeleteDraft::class, CampaignController::class, CreateEvaluationRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** The only campaign stored, whatever its company. */
function storedCampaign(): Campaign
{
    return Campaign::query()->withoutGlobalScopes()->sole();
}

/** @return Illuminate\Support\Collection<int, Participant> */
function sampledParticipants(Campaign $campaign): Illuminate\Support\Collection
{
    return Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get();
}

it('creates a draft with the guides of the work center\'s headcount', function (int $people, array $instruments): void {
    staffOf($this->tenant, $this->plant, $people);
    Log::spy();

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.show', [$this->tenant, storedCampaign()->id]));

    $campaign = storedCampaign();

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->tenant_id)->toBe($this->tenant->id)
        ->and($campaign->work_center_id)->toBe($this->plant->id)
        ->and($campaign->workers)->toBe($people)
        ->and($campaign->instruments)->toBe($instruments)
        ->and($campaign->population)->toBe(Population::All)
        ->and($campaign->created_by)->toBe($this->hr->id)
        ->and(sampledParticipants($campaign))->toHaveCount(0);

    Log::shouldHaveReceived('info')->with('campaign.drafted', ['campaign_id' => $campaign->id, 'work_center_id' => $this->plant->id, 'workers' => $people]);
})->with([
    '15: Guía I' => [15, ['nom035.guide-i']],
    '16: Guías I and II' => [16, ['nom035.guide-i', 'nom035.guide-ii']],
    '50: Guías I and II' => [50, ['nom035.guide-i', 'nom035.guide-ii']],
    '51: Guías I and III' => [51, ['nom035.guide-i', 'nom035.guide-iii']],
]);

it('counts only the active people of the work center', function (): void {
    staffOf($this->tenant, $this->plant, 16);
    Employee::query()->withoutGlobalScopes()->limit(1)->update(['deactivated_at' => now()]);
    staffOf($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), 3);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id]);

    expect(storedCampaign()->workers)->toBe(15)
        ->and(storedCampaign()->instruments)->toBe(['nom035.guide-i']);
});

it('draws a Guía III sample split by gender in proportion', function (): void {
    $people = staffOf($this->tenant, $this->plant, female: 56, male: 42, other: 2);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample'])
        ->assertSessionHasNoErrors();

    $campaign = storedCampaign();
    $sampled = sampledParticipants($campaign);
    $genders = $people->keyBy('id');

    expect($campaign->population)->toBe(Population::Sample)
        ->and($campaign->sample_size)->toBe(80)
        ->and($campaign->quotas)->toEqual(['female' => 45, 'male' => 34, 'other' => 1])
        ->and($sampled)->toHaveCount(80)
        ->and($sampled->every(fn (Participant $participant): bool => $participant->in_sample && $participant->token_hash === null))->toBeTrue()
        ->and($sampled->countBy(fn (Participant $participant): string => $genders[$participant->employee_id]->gender->value)->sortKeys()->all())
        ->toBe(['female' => 45, 'male' => 34, 'other' => 1]);
});

it('draws the sample at random', function (): void {
    staffOf($this->tenant, $this->plant, female: 100, male: 100);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample']);
    $campaign = storedCampaign();
    $first = sampledParticipants($campaign)->pluck('employee_id')->sort()->values()->all();

    $this->actingAs($this->hr)->post(route('tenant.campaigns.redraw', [$this->tenant, $campaign->id]))->assertSessionHasNoErrors()->assertRedirect();

    $second = sampledParticipants($campaign)->pluck('employee_id')->sort()->values()->all();

    // 132 of 200 drawn twice: the same draw again is as likely as never.
    expect($second)->toHaveCount(132)->not->toBe($first);
});

it('offers no sample when Guía III does not apply', function (int $people): void {
    staffOf($this->tenant, $this->plant, $people);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample'])
        ->assertSessionHasErrors(['population' => 'Solo la Guía III se puede aplicar a una muestra; este centro tiene 50 personas o menos.']);

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([16, 50]);

it('redraws only the sample of a draft', function (): void {
    staffOf($this->tenant, $this->plant, 30);
    $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.redraw', [$this->tenant, $campaign->id]))->assertStatus(409);
});

it('refuses a second open evaluation of a work center', function (CampaignStatus $status): void {
    staffOf($this->tenant, $this->plant, 20);
    Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, ...($status === CampaignStatus::Active ? ['status' => $status, 'launched_at' => now(), 'closes_on' => now()->addWeek()->toDateString()] : [])]);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id])
        ->assertSessionHasErrors(['work_center_id' => 'Este centro ya tiene una evaluación en curso o en borrador.']);
})->with([CampaignStatus::Draft, CampaignStatus::Active]);

it('locks the work center before checking it has no open evaluation, so an archive at the same time waits', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    $queries = [];
    Illuminate\Support\Facades\DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id])->assertSessionHasNoErrors();

    $lock = collect($queries)->search(fn (string $sql): bool => str_contains($sql, 'from "work_centers"') && str_ends_with($sql, 'for update'));
    $check = collect($queries)->search(fn (string $sql): bool => str_starts_with($sql, 'select exists') && str_contains($sql, 'from "campaigns"'));

    expect($lock)->toBeInt()->and($check)->toBeInt()->and($lock)->toBeLessThan($check);
});

it('refuses a work center that is archived, empty, or another company\'s', function (Closure $workCenter, string $message): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $workCenter($this->tenant)->id])
        ->assertSessionHasErrors(['work_center_id' => $message]);

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'archived' => [fn (Tenant $tenant): WorkCenter => tap(WorkCenter::factory()->for($tenant)->archived()->create(), fn (WorkCenter $workCenter) => staffOf($tenant, $workCenter, 20)), 'Elige un centro de trabajo activo.'],
    'empty' => [fn (Tenant $tenant): WorkCenter => WorkCenter::factory()->for($tenant)->create(), 'Este centro no tiene personas activas. Da de alta a su personal primero.'],
    'another company' => [fn (): WorkCenter => tap(WorkCenter::factory()->create(), fn (WorkCenter $workCenter) => staffOf(tenantOf($workCenter->tenant_id), $workCenter, 20)), 'Elige un centro de trabajo activo.'],
]);

it('deletes a draft, never a launched campaign', function (): void {
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $active = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)->delete(route('tenant.campaigns.destroy', [$this->tenant, $active->id]))->assertStatus(409);
    $this->actingAs($this->hr)->delete(route('tenant.campaigns.destroy', [$this->tenant, $draft->id]))
        ->assertRedirect(route('tenant.campaigns.index', $this->tenant));

    expect(Campaign::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$active->id]);
});

it('lets a viewer see evaluations but not create, redraw or delete them', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    staffOf($this->tenant, $this->plant, 60);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'population' => Population::Sample, 'sample_size' => 52, 'quotas' => ['female' => 52], 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60]);

    $this->actingAs($viewer)->get(route('tenant.campaigns.index', $this->tenant))->assertOk();
    $this->actingAs($viewer)->get(route('tenant.campaigns.show', [$this->tenant, $draft->id]))->assertOk();
    $this->actingAs($viewer)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id])->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.campaigns.redraw', [$this->tenant, $draft->id]))->assertForbidden();
    $this->actingAs($viewer)->delete(route('tenant.campaigns.destroy', [$this->tenant, $draft->id]))->assertForbidden();
});

it('answers 404 for another company\'s campaign', function (string $method, string $route): void {
    $foreign = Campaign::factory()->create();

    $this->actingAs($this->hr)->{$method}(route($route, [$this->tenant, $foreign->id]))->assertNotFound();
})->with([
    ['get', 'tenant.campaigns.show'],
    ['post', 'tenant.campaigns.redraw'],
    ['delete', 'tenant.campaigns.destroy'],
]);

it('sends guests to the login', function (): void {
    $this->get(route('tenant.campaigns.index', $this->tenant))->assertRedirect(route('login'));
    $this->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id])->assertRedirect(route('login'));
});

it('redraws or deletes nothing once the draft was launched by another request', function (string $method, string $route): void {
    staffOf($this->tenant, $this->plant, 60);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample']);
    $campaign = storedCampaign();
    $raced = false;

    Illuminate\Support\Facades\Event::listen('eloquent.retrieved: '.Campaign::class, function () use ($campaign, &$raced): void {
        if (! $raced) {
            $raced = true;
            Illuminate\Support\Facades\DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'active', 'launched_at' => now(), 'closes_on' => now()->addWeek()->toDateString()]);
        }
    });

    $this->actingAs($this->hr)->{$method}(route($route, [$this->tenant, $campaign->id]))->assertStatus(409);

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(sampledParticipants($campaign))->toHaveCount(53);
})->with([['post', 'tenant.campaigns.redraw'], ['delete', 'tenant.campaigns.destroy']]);
