<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\CommitteeController;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

mutates(CommitteeController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->other = WorkCenter::factory()->for($this->tenant)->create();
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function actUrl(WorkCenter $center): string
{
    return route('tenant.telework.committee.act', [test()->tenant, $center->id]);
}

function committeeWithAct(WorkCenter $center, bool $file = true): SafetyCommittee
{
    $path = "committees/{$center->tenant_id}/{$center->id}/act-test.pdf";
    $file && Storage::disk(config()->string('filesystems.telework_files'))->put($path, "%PDF-1.4\n%%EOF\n");

    return SafetyCommittee::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'act_path' => $path]);
}

it('serves the constitution act privately to owner, hr and the assigned committee', function (): void {
    committeeWithAct($this->center);

    foreach ([memberOf($this->tenant, TenantRole::Owner), memberOf($this->tenant, TenantRole::Hr), $this->cora] as $user) {
        $response = $this->actingAs($user)->get(actUrl($this->center))->assertOk();
        expect((string) $response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and((string) $response->headers->get('Content-Type'))->toContain('application/pdf');
    }
});

it('hides the act from the viewer, an unassigned committee user and another company', function (): void {
    committeeWithAct($this->center);
    committeeWithAct($this->other);
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(actUrl($this->center))->assertNotFound();
    $this->actingAs($this->cora)->get(actUrl($this->other))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.telework.committee.act', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.telework.committee.act', [$this->tenant, 'nope']))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.telework.committee.act', [$this->tenant, (string) Str::uuid()]))->assertNotFound();
});

it('is a 404 when the committee has no act or no committee exists', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $this->actingAs($hr)->get(actUrl($this->other))->assertNotFound();

    committeeWithAct($this->other, file: false);
    SafetyCommittee::query()->where('work_center_id', $this->other->id)->update(['act_path' => null]);
    $this->actingAs($hr)->get(actUrl($this->other))->assertNotFound();
});
