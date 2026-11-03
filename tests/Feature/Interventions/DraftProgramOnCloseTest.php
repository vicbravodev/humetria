<?php

declare(strict_types=1);

use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Listeners\DraftProgramOnClose;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

mutates(DraftProgramOnClose::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** The evaluation of the company as it was before it closed, with its questionnaires. */
function openedEvaluation(Tenant $tenant, array $answers, int $count = 5): Campaign
{
    $campaign = closedEvaluationWith($tenant, $answers, $count);
    $campaign->update(['status' => 'active', 'closed_at' => null]);

    return $campaign;
}

it('drafts the Programa when the evaluation closes, by the system', function (): void {
    $campaign = openedEvaluation($this->tenant, nom035Answers('nom035.guide-ii', 'always'));

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $campaign->id]))->assertSessionHasNoErrors();

    workIn($this->tenant);
    $program = Program::query()->sole();
    expect($program->campaign_id)->toBe($campaign->id)
        ->and($program->created_by)->toBeNull()
        ->and(ProgramItem::query()->count())->toBeGreaterThan(0);
});

it('drafts nothing when nobody falls in Medio or more', function (): void {
    $campaign = openedEvaluation($this->tenant, nom035HealthyAnswers('nom035.guide-ii'));

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $campaign->id]))->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(Program::query()->count())->toBe(0);
});

it('drafts an empty Programa when the questionnaires are under the minimum', function (): void {
    $campaign = openedEvaluation($this->tenant, nom035Answers('nom035.guide-ii', 'always'), 4);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $campaign->id]))->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(Program::query()->sole()->highest_level)->toBeNull()
        ->and(ProgramItem::query()->count())->toBe(0);
});

it('drafts nothing for a campaign of only the Guía I', function (): void {
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $trauma->id]))->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(Program::query()->count())->toBe(0);
});

it('drafts nothing for a blocked company', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));
    $this->tenant->update(['deactivated_at' => now()]);

    resolve(DraftProgramOnClose::class)->handle(new CampaignClosed($this->tenant->id, $campaign->id));

    expect(Program::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('works inside the company of the event, whatever company the worker was in', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));

    resolve(DraftProgramOnClose::class)->handle(new CampaignClosed($this->tenant->id, $campaign->id));

    expect(resolve(CurrentTenant::class)->id())->toBe($this->tenant->id)
        ->and(Program::query()->count())->toBe(1);
});

it('runs queued and is listening for the closing of an evaluation', function (): void {
    Event::fake([CampaignClosed::class]);

    expect(resolve(DraftProgramOnClose::class))->toBeInstanceOf(ShouldQueue::class);
    Event::assertListening(CampaignClosed::class, DraftProgramOnClose::class);
});
