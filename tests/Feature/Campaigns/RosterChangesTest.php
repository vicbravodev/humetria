<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CancelParticipation;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Listeners\CancelOnDeactivation;
use App\Modules\Campaigns\Listeners\CancelOnMove;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;

mutates(CancelParticipation::class, CancelOnDeactivation::class, CancelOnMove::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-05 15:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->campaign = Campaign::factory()->active('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->employee = staffOf($this->tenant, $this->plant, 1)->first();
    $this->participant = Participant::factory()->create(['campaign_id' => $this->campaign->id, 'employee_id' => $this->employee->id, 'token_hash' => Participant::hashToken('the-token')]);
    ParticipantInstrument::factory()->completed()->create(['participant_id' => $this->participant->id, 'instrument' => 'nom035.guide-i']);
    ParticipantInstrument::factory()->create(['participant_id' => $this->participant->id, 'instrument' => 'nom035.guide-ii']);
});

/** @return array<string, string> */
function statusesOf(Participant $participant): array
{
    return ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $participant->id)->orderBy('instrument')->get()
        ->mapWithKeys(fn (ParticipantInstrument $guide): array => [$guide->instrument => $guide->status->value])->all();
}

it('cancels the guides a person had pending when they are deactivated, keeping what they answered', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    expect(statusesOf($this->participant))->toBe(['nom035.guide-i' => 'completed', 'nom035.guide-ii' => 'cancelled'])
        ->and(ParticipantInstrument::query()->withoutGlobalScopes()->where('status', InstrumentStatus::Cancelled)->sole()->cancelled_at?->toIso8601String())->toBe('2026-11-05T15:00:00+00:00');

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn ($page) => $page->where('unavailable', 'done'));
});

it('leaves closed evaluations as they ended', function (): void {
    $this->campaign->update(['status' => 'closed', 'closed_at' => now()]);

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]));

    expect(statusesOf($this->participant))->toBe(['nom035.guide-i' => 'completed', 'nom035.guide-ii' => 'pending']);
});

it('cancels the guides of a person moved to another work center, only in their old work center\'s evaluations', function (): void {
    $warehouse = WorkCenter::factory()->for($this->tenant)->create();
    $elsewhere = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $warehouse->id]);
    $there = Participant::factory()->create(['campaign_id' => $elsewhere->id, 'employee_id' => $this->employee->id]);
    ParticipantInstrument::factory()->create(['participant_id' => $there->id]);

    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), [
        'name' => $this->employee->name,
        'email' => $this->employee->email,
        'mobile' => $this->employee->mobile,
        'gender' => $this->employee->gender->value,
        'work_center_id' => $warehouse->id,
        'area' => 'Almacén',
        'job_title' => $this->employee->job_title,
    ])->assertSessionHasNoErrors();

    expect(statusesOf($this->participant))->toBe(['nom035.guide-i' => 'completed', 'nom035.guide-ii' => 'cancelled'])
        ->and(statusesOf($there))->toBe(['nom035.guide-i' => 'pending']);
});

it('keeps the guides of a person whose area changed', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), [
        'name' => $this->employee->name,
        'email' => $this->employee->email,
        'mobile' => $this->employee->mobile,
        'gender' => $this->employee->gender->value,
        'work_center_id' => $this->plant->id,
        'area' => 'Otra área',
        'job_title' => $this->employee->job_title,
    ])->assertSessionHasNoErrors();

    expect(statusesOf($this->participant))->toBe(['nom035.guide-i' => 'completed', 'nom035.guide-ii' => 'pending']);
});

it('touches only that company\'s evaluations', function (): void {
    $other = Participant::factory()->create();
    ParticipantInstrument::factory()->create(['participant_id' => $other->id]);
    workIn($this->tenant);

    resolve(CancelParticipation::class)->handle($other->employee_id);

    expect(statusesOf($other))->toBe(['nom035.guide-i' => 'pending']);
});
