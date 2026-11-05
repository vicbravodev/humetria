<?php

declare(strict_types=1);

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['procedure' => 'Recibimos su queja y la atiende una persona designada.', 'acknowledge_within_days' => 2, 'resolve_within_days' => 20, 'retention_months' => 18]);
});

it('shows whoever handles complaints how the channel is handled (board V4-Menu-Roles, «Cómo se atiende»), and nothing of a case', function (TenantRole $role): void {
    Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'description' => 'Un secreto del caso']);

    $response = $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.complaints.procedure', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/procedure')
            ->where('procedure', 'Recibimos su queja y la atiende una persona designada.')
            ->where('acknowledgeWithinDays', 2)
            ->where('resolveWithinDays', 20)
            ->where('retentionMonths', 18));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('secreto');
})->with([TenantRole::Complaints, TenantRole::Committee, TenantRole::Hr, TenantRole::Owner]);

it('gives the deadlines of the platform when the channel was never set up', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);

    $this->actingAs(memberOf($other, TenantRole::Complaints))
        ->get(route('tenant.complaints.procedure', $other))
        ->assertInertia(fn (Assert $page) => $page->where('acknowledgeWithinDays', 3)->where('resolveWithinDays', 30)->where('retentionMonths', 24)->has('procedure'));
});

it('keeps the page from whoever cannot handle complaints', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.complaints.procedure', $this->tenant))
        ->assertForbidden();
});
