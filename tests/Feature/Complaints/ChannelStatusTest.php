<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\ChannelStatusPages;
use App\Modules\Complaints\Support\ComplaintStatistics;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ComplaintStatistics::class, ChannelStatusPages::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme Industrial']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['published_on' => '2026-02-01', 'procedure' => 'Así se atiende.', 'acknowledge_within_days' => 3, 'resolve_within_days' => 30, 'retention_months' => 36]);
    workIn($this->tenant);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function some(object $test, int $count, array $attributes = []): void
{
    Complaint::factory()->count($count)->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, ...$attributes]);
}

it('tells the manager which of the four things the channel needs are in place', function (): void {
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checklist.0', ['key' => 'procedure', 'label' => 'Procedimiento por escrito', 'done' => true])
            ->where('checklist.1', ['key' => 'handlers', 'label' => 'Al menos una persona que atienda', 'done' => true])
            ->where('checklist.2', ['key' => 'coverage', 'label' => 'Alguien independiente para cada opción de «contra quién»', 'done' => false])
            ->where('checklist.3', ['key' => 'settings', 'label' => 'Plazos y conservación definidos', 'done' => true]));
});

it('marks the coverage done once nobody is left out, and the handlers undone with none', function (): void {
    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('checklist.1.done', false)->where('checklist.2.done', false));

    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('checklist.1.done', true)->where('checklist.2.done', true));
});

it('counts the acknowledgements past their deadline, and nothing about which cases', function (): void {
    some($this, 2, ['received_on' => '2026-11-10']);
    some($this, 1, ['received_on' => '2026-11-17']);
    some($this, 1, ['received_on' => '2026-11-01', 'status' => 'in_review', 'acknowledged_at' => now()]);

    $page = $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('overdueAcknowledgements', 2));

    expect(json_encode($page->viewData('page')['props']))->not->toContain('reference');
});

it('summarizes by type and by status with the minimum of five', function (): void {
    some($this, 12, ['type' => 'workplace_violence']);
    some($this, 5, ['type' => 'unfavorable_practice']);
    some($this, 6, ['status' => 'in_review', 'type' => 'workplace_violence', 'acknowledged_at' => now()]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.minimum', 5)
            ->where('summary.total', 23)
            ->where('summary.byType', [['value' => 'unfavorable_practice', 'label' => 'Queja por una práctica opuesta a un entorno favorable', 'count' => 5], ['value' => 'workplace_violence', 'label' => 'Denuncia de violencia laboral', 'count' => 18], ['value' => 'traumatic_event_notice', 'label' => 'Aviso de un acontecimiento traumático severo', 'count' => 0]])
            ->where('summary.byStatus', [['value' => 'new', 'label' => 'Nuevas', 'count' => 17], ['value' => 'in_review', 'label' => 'En revisión', 'count' => 6], ['value' => 'closed', 'label' => 'Cerradas', 'count' => 0]]));
});

it('hides what is under the minimum and what would give it away, and the total under the minimum', function (): void {
    some($this, 6, ['type' => 'workplace_violence']);
    some($this, 2, ['type' => 'unfavorable_practice']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 8)
            ->where('summary.byType.0.count', null)
            ->where('summary.byType.1.count', null)
            ->where('summary.byType.2.count', 0));

    Complaint::query()->delete();
    some($this, 4, ['type' => 'workplace_violence']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('summary.total', null)->where('summary.byType.1.count', null)->where('summary.byStatus.0.count', null));
});

it('counts the cases already purged: their skeleton still says the channel operated', function (): void {
    some($this, 5, ['type' => 'workplace_violence', 'status' => 'closed', 'resolution' => 'measures_taken', 'closed_on' => '2024-01-01', 'closed_at' => '2024-01-01 12:00:00', 'description' => null, 'purged_on' => '2026-11-20']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('summary.byStatus.2.count', 5)->where('summary.total', 5));
});

it('gives no breakdown by center, category, who it is against or day', function (): void {
    some($this, 12, ['type' => 'workplace_violence', 'category' => 'mistreatment', 'respondent_kind' => 'hr']);

    $summary = $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))->viewData('page')['props']['summary'];

    expect(array_keys($summary))->toBe(['minimum', 'total', 'byType', 'byStatus']);
    expect(json_encode($summary))->not->toContain('mistreatment')->not->toContain('center')->not->toContain('hr');
});

it('shows the evidence of the mechanism to the people who manage the channel, with no case in it, and audits the reading', function (): void {
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    some($this, 1, ['description' => 'Una historia reservada del turno', 'reference' => 'SECRET12', 'reporter_name' => 'Rosa Aguilar', 'identity_mode' => 'confidential']);
    some($this, 5);

    $response = $this->actingAs($this->owner)->get(route('tenant.complaints.evidence', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/evidence')
            ->where('company', 'Acme Industrial')
            ->where('channel.status', 'active')
            ->where('channel.url', $this->channel->publicUrl())
            ->where('channel.publishedOn', '2026-02-01')
            ->where('channel.procedure', 'Así se atiende.')
            ->where('channel.acknowledgeWithinDays', 3)
            ->where('channel.resolveWithinDays', 30)
            ->where('channel.retentionMonths', 36)
            ->where('handlers', ['count' => 1, 'independent' => false])
            ->has('types', 3)
            ->has('coverage', 6)
            ->where('summary.total', 6)
            ->has('generatedOn'));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('reservada')->not->toContain('SECRET12')->not->toContain('Rosa');
    expect(AuditEntry::query()->where('event', 'complaint_channel.evidence_viewed')->count())->toBe(1);
});

it('says there is an independent handler when one is neither HR nor the owner', function (): void {
    $outsider = memberOf($this->tenant, TenantRole::Complaints);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $outsider->id]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.evidence', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('handlers', ['count' => 1, 'independent' => true]));
});

it('records that the evidence was printed', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.complaints.evidence.export', $this->tenant))->assertRedirect(route('tenant.complaints.evidence', $this->tenant));

    expect(AuditEntry::query()->where('event', 'complaint_channel.evidence_exported')->count())->toBe(1);
});

it('keeps the evidence and the summary to the people who manage the channel', function (TenantRole $role): void {
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->get(route('tenant.complaints.evidence', $this->tenant))->assertForbidden();
    $this->actingAs($person)->post(route('tenant.complaints.evidence.export', $this->tenant))->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('never mixes in the cases of another company', function (): void {
    $other = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
    Complaint::factory()->count(9)->create(['tenant_id' => $other->id, 'channel_id' => $channel->id]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('summary.total', 0));
});
