<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Actions\SignAssessment;
use App\Modules\Seating\Events\RiskAssessmentSigned;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(SignAssessment::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
    $this->draft = RiskAssessment::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id,
        'scores' => ['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'footwear' => 1, 'movement_space' => 2, 'discomfort' => 1, 'pauses' => 2],
        'other_aspects' => 'Ninguno.', 'verified_on' => '2026-02-27',
    ]);
});

function signUrl(?RiskAssessment $assessment = null): string
{
    return route('tenant.seating.assessments.sign', [test()->tenant, ($assessment ?? test()->draft)->id]);
}

it('signs: computes the total and the level, records who and when, and publishes the event', function (): void {
    Event::fake([RiskAssessmentSigned::class]);
    $this->actingAs($this->cora);

    $this->post(signUrl())->assertRedirect()->assertSessionHasNoErrors();

    $signed = RiskAssessment::query()->sole();
    expect($signed->status->value)->toBe('signed')
        ->and($signed->total)->toBe(14)
        ->and($signed->level)->toBe(RiskLevel::Medium)
        ->and($signed->instrument_version)->toBe('dof-2025-07-17')
        ->and($signed->signed_by)->toBe($this->cora->id)
        ->and($signed->signed_at?->toDateTimeString())->toBe('2026-03-01 12:00:00')
        ->and($signed->reassessment_due)->toBeFalse();
    Event::assertDispatched(RiskAssessmentSigned::class, fn (RiskAssessmentSigned $event): bool => $event->employeeId === $this->ana->id && $event->workCenterId === $this->center->id && $event->level === RiskLevel::Medium);
});

it('refuses to sign a draft of a position the person no longer holds, and signs it once the draft follows them', function (): void {
    $this->actingAs($this->cora);
    $other = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    PositionAssignment::query()->where('employee_id', $this->ana->id)->update(['ends_on' => '2026-02-28']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $other->id, 'starts_on' => '2026-03-01']);

    $this->post(signUrl())->assertSessionHasErrors('sign');
    expect($this->draft->refresh()->status->value)->toBe('draft');

    $this->draft->forceFill(['position_id' => $other->id])->save();
    $this->post(signUrl())->assertSessionHasNoErrors();
    expect($this->draft->refresh()->status->value)->toBe('signed');
});

it('refuses to sign the draft of a person with no current position', function (): void {
    $this->actingAs($this->cora);
    PositionAssignment::query()->where('employee_id', $this->ana->id)->update(['ends_on' => '2026-02-28']);

    $this->post(signUrl())->assertSessionHasErrors('sign');

    expect($this->draft->refresh()->status->value)->toBe('draft');
});

it('maps the totals of the DOF to their level at the edges', function (array $scores, int $total, RiskLevel $level): void {
    $this->draft->forceFill(['scores' => $scores])->save();
    $this->actingAs($this->cora);

    $this->post(signUrl())->assertSessionHasNoErrors();

    $signed = RiskAssessment::query()->sole();
    expect($signed->total)->toBe($total)->and($signed->level)->toBe($level);
})->with([
    'lowest' => [['standing_time' => 1, 'posture_change' => 1, 'surface' => 1, 'footwear' => 1, 'movement_space' => 1, 'discomfort' => 1, 'pauses' => 1], 7, RiskLevel::Low],
    'first medium' => [['standing_time' => 2, 'posture_change' => 1, 'surface' => 1, 'footwear' => 1, 'movement_space' => 1, 'discomfort' => 1, 'pauses' => 1], 8, RiskLevel::Medium],
    'last medium' => [['standing_time' => 2, 'posture_change' => 2, 'surface' => 2, 'footwear' => 2, 'movement_space' => 2, 'discomfort' => 2, 'pauses' => 2], 14, RiskLevel::Medium],
    'first high' => [['standing_time' => 3, 'posture_change' => 2, 'surface' => 2, 'footwear' => 2, 'movement_space' => 2, 'discomfort' => 2, 'pauses' => 2], 15, RiskLevel::High],
    'highest' => [['standing_time' => 3, 'posture_change' => 3, 'surface' => 3, 'footwear' => 3, 'movement_space' => 3, 'discomfort' => 3, 'pauses' => 3], 21, RiskLevel::High],
]);

it('lets only a member assigned to the center sign: an owner not assigned cannot, an assigned owner can', function (): void {
    $owner = memberOf($this->tenant, TenantRole::Owner);
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $elsewhere->id]);

    foreach ([$owner, $hr, memberOf($this->tenant, TenantRole::Viewer)] as $user) {
        $this->actingAs($user)->post(signUrl())->assertForbidden();
    }
    // A committee member of another center does not even find it.
    $this->actingAs($elsewhere)->post(signUrl())->assertNotFound();
    expect(RiskAssessment::query()->sole()->status->value)->toBe('draft');

    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $owner->id]);
    $this->actingAs($owner)->post(signUrl())->assertSessionHasNoErrors();

    expect(RiskAssessment::query()->sole()->signed_by)->toBe($owner->id);
});

it('signs once: a second request, a double click or another tab leaves one signed assessment untouched', function (): void {
    $this->actingAs($this->cora);
    $this->post(signUrl());
    $first = RiskAssessment::query()->sole();
    $this->travelTo('2026-03-01 12:05:00');
    $other = memberOf($this->tenant, TenantRole::Owner);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $other->id]);

    $this->post(signUrl())->assertRedirect();
    $this->actingAs($other)->post(signUrl())->assertRedirect();

    $again = RiskAssessment::query()->sole();
    expect($again->signed_at?->toDateTimeString())->toBe($first->signed_at?->toDateTimeString())
        ->and($again->signed_by)->toBe($this->cora->id)
        ->and(AuditEntry::query()->where('event', 'seating.assessment_signed')->count())->toBe(1);
});

it('does not edit a signed assessment: not by the page and not in the database', function (): void {
    $this->actingAs($this->cora);
    $this->post(signUrl());
    $signed = RiskAssessment::query()->sole();

    $this->put(route('tenant.seating.assessments.update', [$this->tenant, $signed->id]), ['scores' => ['pauses' => 1]])->assertSessionHasErrors('status');

    expect(RiskAssessment::query()->sole()->scores['pauses'])->toBe(2)
        ->and(fn () => DB::transaction(fn () => DB::table('risk_assessments')->update(['total' => 7])))->toThrow(QueryException::class, 'signed assessment is final')
        ->and(fn () => DB::transaction(fn () => DB::table('risk_assessments')->update(['scores' => json_encode(['pauses' => 1])])))->toThrow(QueryException::class, 'signed assessment is final')
        ->and(fn () => DB::transaction(fn () => DB::table('risk_assessments')->delete()))->toThrow(QueryException::class, 'signed assessment is final');
    // The two marks that are not part of the evidence can change.
    DB::table('risk_assessments')->update(['reassessment_due' => true]);
    expect(RiskAssessment::query()->sole()->reassessment_due)->toBeTrue();
});

it('assesses again: a new draft, and the previous signed one stays in the history', function (): void {
    $this->actingAs($this->cora);
    $this->post(signUrl());
    $this->travelTo('2026-09-01 12:00:00');

    $this->post(route('tenant.seating.assessments.store', [$this->tenant, $this->center->id]), ['employee_id' => $this->ana->id])->assertSessionHasNoErrors();
    $new = RiskAssessment::query()->where('status', 'draft')->sole();
    $this->put(route('tenant.seating.assessments.update', [$this->tenant, $new->id]), ['scores' => ['footwear' => 3], 'other_aspects' => 'Ninguno.', 'verified_on' => '2026-09-01']);
    $this->post(signUrl($new))->assertSessionHasNoErrors();

    $all = RiskAssessment::query()->orderBy('signed_at')->get();
    expect($all)->toHaveCount(2)
        ->and($all->pluck('status.value')->all())->toBe(['signed', 'signed'])
        ->and($all[0]->scores['footwear'])->toBe(1)
        ->and($all[1]->scores['footwear'])->toBe(3);
});

it('refuses to sign with a score or a Quinta C datum missing', function (string $missing): void {
    match ($missing) {
        'a score' => $this->draft->forceFill(['scores' => ['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'movement_space' => 2, 'discomfort' => 1, 'pauses' => 2]])->save(),
        'the verification date' => $this->draft->forceFill(['verified_on' => null])->save(),
        'the other aspects' => $this->draft->forceFill(['other_aspects' => '  '])->save(),
        'the tools' => $this->position->forceFill(['tools' => null])->save(),
        'the physical load' => $this->position->forceFill(['physical_load' => null])->save(),
        'the attention level' => $this->position->forceFill(['attention_level' => null])->save(),
        'every agent of the area' => $this->position->forceFill(['area_conditions' => ['noise' => null, 'lighting' => null]])->save(),
    };
    $this->actingAs($this->cora);

    $this->post(signUrl())->assertSessionHasErrors('sign');

    expect(RiskAssessment::query()->sole()->status->value)->toBe('draft');
})->with(['a score', 'the verification date', 'the other aspects', 'the tools', 'the physical load', 'the attention level', 'every agent of the area']);

it('freezes the facts of Quinta C at the signature: later changes to the position do not rewrite it', function (): void {
    $this->actingAs($this->cora);
    $this->post(signUrl());
    $this->position->forceFill(['name' => 'Renombrada', 'tools' => 'Otra cosa'])->save();
    $this->ana->forceFill(['name' => 'Ana Otra'])->save();

    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $this->draft->id]))
        ->assertInertia(fn ($page) => $page->where('facts', fn ($facts) => collect($facts)->contains(fn ($fact) => str_contains((string) $fact['value'], 'Cajera')) && ! collect($facts)->contains(fn ($fact) => str_contains((string) $fact['value'], 'Renombrada'))));
});

it('writes the signature to the audit trail and the log with ids and level only', function (): void {
    Log::spy();
    $this->actingAs($this->cora);

    $this->post(signUrl());

    $record = ['assessment_id' => $this->draft->id, 'work_center_id' => $this->center->id, 'level' => 'medium'];
    expect(AuditEntry::query()->where('event', 'seating.assessment_signed')->sole()->metadata)->toEqual($record)
        ->and(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('Ana Ruiz')->not->toContain('"scores"')->not->toContain('Ninguno');
    Log::shouldHaveReceived('info')->with('seating.assessment_signed', $record)->once();
});

it('refuses in the database an inconsistent signed row', function (): void {
    $attempts = [
        'risk_assessments_signed_is_complete' => fn () => DB::table('risk_assessments')->update(['status' => 'signed']),
        'risk_assessments_total_range' => fn () => DB::table('risk_assessments')->update(['total' => 22]),
        'risk_assessments_level_known' => fn () => DB::table('risk_assessments')->update(['level' => 'severe']),
    ];

    foreach ($attempts as $constraint => $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
});

it('does not touch the assessments of another company', function (): void {
    $foreign = RiskAssessment::factory()->create();
    $this->actingAs($this->cora);

    $this->post(signUrl($foreign))->assertNotFound();
    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $foreign->id]))->assertNotFound();
});
