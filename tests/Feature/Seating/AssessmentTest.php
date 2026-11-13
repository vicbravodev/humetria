<?php

declare(strict_types=1);

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Actions\FlagReassessment;
use App\Modules\Seating\Actions\SaveAssessmentDraft;
use App\Modules\Seating\Actions\StartAssessment;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Http\Controllers\AssessmentController;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SuggestedMeasures;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(StartAssessment::class, SaveAssessmentDraft::class, FlagReassessment::class, SuggestedMeasures::class, AssessmentController::class);

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
});

function startFor(string $employeeId, ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.seating.assessments.store', [test()->tenant, ($center ?? test()->center)->id]), ['employee_id' => $employeeId]);
}

it('starts an assessment that inherits the position conditions and the person own answers', function (): void {
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'footwear' => 2, 'discomfort' => 3]);
    $this->actingAs($this->cora);

    startFor($this->ana->id)->assertRedirect()->assertSessionHasNoErrors();

    $draft = RiskAssessment::query()->sole();
    expect($draft->status->value)->toBe('draft')
        ->and($draft->employee_id)->toBe($this->ana->id)
        ->and($draft->position_id)->toBe($this->position->id)
        ->and($draft->work_center_id)->toBe($this->center->id)
        ->and($draft->scores)->toEqual(['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'footwear' => 2, 'movement_space' => 2, 'discomfort' => 3, 'pauses' => 2])
        ->and($draft->person_provided_health_data)->toBeTrue()
        ->and($draft->total)->toBeNull()
        ->and($draft->level)->toBeNull();
});

it('uses discomfort 1 and flags it when the person gave no health data', function (): void {
    $this->actingAs($this->cora);

    startFor($this->ana->id)->assertSessionHasNoErrors();

    $draft = RiskAssessment::query()->sole();
    expect($draft->person_provided_health_data)->toBeFalse()
        ->and($draft->scores['discomfort'])->toBe(1)
        ->and($draft->scores)->not->toHaveKey('footwear');
});

it('uses the latest answer of the person, not the first', function (): void {
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'footwear' => 1, 'discomfort' => 1, 'submitted_at' => '2026-01-01 12:00:00']);
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'footwear' => 3, 'discomfort' => 2, 'submitted_at' => '2026-02-01 12:00:00']);
    $this->actingAs($this->cora);

    startFor($this->ana->id);

    expect(RiskAssessment::query()->sole()->scores)->toMatchArray(['footwear' => 3, 'discomfort' => 2]);
});

it('leaves a condition empty when the position gave no default for it', function (): void {
    $this->position->forceFill(['default_surface' => null])->save();
    $this->actingAs($this->cora);

    startFor($this->ana->id);

    expect(RiskAssessment::query()->sole()->scores)->not->toHaveKey('surface');
});

it('keeps one draft per person: starting again opens the same one', function (): void {
    $this->actingAs($this->cora);
    startFor($this->ana->id);
    $first = RiskAssessment::query()->sole();

    startFor($this->ana->id)->assertRedirect(route('tenant.seating.assessments.show', [$this->tenant, $first->id]));

    expect(RiskAssessment::query()->count())->toBe(1);
});

it('points a pending draft at the position the person holds now, in the same center', function (): void {
    $this->actingAs($this->cora);
    startFor($this->ana->id);
    $draft = RiskAssessment::query()->sole();
    $other = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Almacenista', 'default_standing_time' => 1, 'default_surface' => null]);
    PositionAssignment::query()->where('employee_id', $this->ana->id)->update(['ends_on' => '2026-02-28']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $other->id, 'starts_on' => '2026-03-01']);

    startFor($this->ana->id)->assertRedirect(route('tenant.seating.assessments.show', [$this->tenant, $draft->id]));

    $draft->refresh();
    expect(RiskAssessment::query()->count())->toBe(1)
        ->and($draft->position_id)->toBe($other->id)
        ->and($draft->scores['standing_time'])->toBe(1)
        ->and($draft->scores)->not->toHaveKey('surface');
});

it('moves a pending draft with the person to the center and position they hold now', function (): void {
    $this->actingAs($this->cora);
    startFor($this->ana->id);
    $draft = RiskAssessment::query()->sole();
    $south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $southPosition = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $south->id, 'name' => 'Cajera Sur']);
    $this->ana->forceFill(['work_center_id' => $south->id])->save();
    PositionAssignment::query()->where('employee_id', $this->ana->id)->update(['ends_on' => '2026-02-28']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $southPosition->id, 'starts_on' => '2026-03-01']);
    $southCora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $south->id, 'user_id' => $southCora->id]);

    $this->actingAs($southCora);
    startFor($this->ana->id, $south)->assertRedirect(route('tenant.seating.assessments.show', [$this->tenant, $draft->id]))->assertSessionHasNoErrors();

    $draft->refresh();
    expect(RiskAssessment::query()->count())->toBe(1)
        ->and($draft->work_center_id)->toBe($south->id)
        ->and($draft->position_id)->toBe($southPosition->id);
});

it('refuses a person with no position, from another center, deactivated, or in a position the nature of the work exempts', function (string $case): void {
    $person = match ($case) {
        'no position' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
        'another center' => (function () {
            $other = WorkCenter::factory()->for($this->tenant)->create();
            $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id]);
            PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'position_id' => StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id])->id]);

            return $employee;
        })(),
        'exempt position' => (function () {
            $exempt = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'nature_allows' => false, 'nature_reason' => 'Prensa', 'placement' => null]);
            $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
            PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'position_id' => $exempt->id]);

            return $employee;
        })(),
    };
    $this->actingAs($this->cora);

    startFor($person->id)->assertSessionHasErrors('employee_id');

    expect(RiskAssessment::query()->count())->toBe(0);
})->with(['no position', 'another center', 'exempt position']);

it('saves the draft: scores, other aspects and the verification date', function (): void {
    $this->actingAs($this->cora);
    startFor($this->ana->id);
    $draft = RiskAssessment::query()->sole();

    $this->put(route('tenant.seating.assessments.update', [$this->tenant, $draft->id]), ['scores' => ['footwear' => 2, 'discomfort' => 3], 'other_aspects' => 'Embarazo de 20 semanas.', 'verified_on' => '2026-02-27'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $fresh = RiskAssessment::query()->sole();
    expect($fresh->scores)->toMatchArray(['footwear' => 2, 'discomfort' => 3, 'surface' => 3])
        ->and($fresh->other_aspects)->toBe('Embarazo de 20 semanas.')
        ->and($fresh->verified_on?->toDateString())->toBe('2026-02-27');
});

it('rejects scores outside 1 to 3, an unknown condition and a verification in the future', function (array $bad, string $field): void {
    $this->actingAs($this->cora);
    startFor($this->ana->id);
    $draft = RiskAssessment::query()->sole();

    $this->put(route('tenant.seating.assessments.update', [$this->tenant, $draft->id]), $bad)->assertSessionHasErrors($field);
})->with([
    'a 0' => [['scores' => ['footwear' => 0]], 'scores.footwear'],
    'a 4' => [['scores' => ['pauses' => 4]], 'scores.pauses'],
    'unknown condition' => [['scores' => ['mood' => 2]], 'scores.mood'],
    'tomorrow' => [['verified_on' => '2026-03-02'], 'verified_on'],
    'long text' => [['other_aspects' => str_repeat('a', 2001)], 'other_aspects'],
]);

it('only flags reassessment and keeps the signed assessment current when the person reports more discomfort', function (int $discomfort, bool $flagged): void {
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $before = $signed->scores;
    $token = resolve(IssuePortalLink::class)->handle($this->ana->id);

    $this->post(route('portal.seating.inputs.store', $token), ['footwear' => $before['footwear'], 'discomfort' => $discomfort, 'accepted' => '1'])->assertSessionHasNoErrors();

    $fresh = RiskAssessment::query()->sole();
    expect($fresh->reassessment_due)->toBe($flagged)
        ->and($fresh->scores)->toEqual($before)
        ->and($fresh->status->value)->toBe('signed');
})->with([
    'the same' => [1, false],
    'more' => [2, true],
]);

it('flags reassessment when the footwear answer changes', function (): void {
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $token = resolve(IssuePortalLink::class)->handle($this->ana->id);

    $this->post(route('portal.seating.inputs.store', $token), ['footwear' => $signed->scores['footwear'] + 1, 'discomfort' => 1, 'accepted' => '1']);

    expect(RiskAssessment::query()->sole()->reassessment_due)->toBeTrue();
});

it('flags reassessment when the person changes position, and not when they take their first', function (): void {
    $signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $other = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Repartidora']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $assignment = PositionAssignment::query()->where('employee_id', $this->ana->id)->sole();

    $this->delete(route('tenant.seating.assignments.destroy', [$this->tenant, $assignment->id]));
    expect($signed->fresh()?->reassessment_due)->toBeFalse();

    $this->post(route('tenant.seating.positions.assignments.store', [$this->tenant, $other->id]), ['employee_ids' => [$this->ana->id]])->assertSessionHasNoErrors();

    expect($signed->fresh()?->reassessment_due)->toBeTrue()->and($signed->fresh()?->status->value)->toBe('signed');
});

it('flags nothing for a person with no signed assessment', function (): void {
    $token = resolve(IssuePortalLink::class)->handle($this->ana->id);

    $this->post(route('portal.seating.inputs.store', $token), ['footwear' => 3, 'discomfort' => 3, 'accepted' => '1'])->assertSessionHasNoErrors();

    expect(RiskAssessment::query()->count())->toBe(0);
});

it('suggests measures by level, labelled as a suggestion of Humetría', function (RiskLevel $level, array $kinds): void {
    expect(SuggestedMeasures::for($level))->toBe($kinds)->and(SuggestedMeasures::LABEL)->toBe('Sugerencia de Humetría');
})->with([
    'low' => [RiskLevel::Low, [MeasureKind::ActiveBreaks]],
    'medium' => [RiskLevel::Medium, [MeasureKind::TaskRotation, MeasureKind::ErgonomicFootwear, MeasureKind::ActiveBreaks]],
    'high' => [RiskLevel::High, [MeasureKind::WorkstationDesign, MeasureKind::TaskRotation, MeasureKind::ErgonomicFootwear, MeasureKind::CushionedFloor, MeasureKind::ActiveBreaks]],
]);

it('lists the people of the center with their assessment state, paginated and searchable on the server', function (): void {
    foreach (range(1, 16) as $n) {
        $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => sprintf('Persona %02d', $n)]);
        PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'position_id' => $this->position->id]);
    }
    RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($this->cora);

    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seating/assessments')->where('people.total', 17)->where('people.per_page', 15)->has('people.data', 15)
            ->where('people.data.0', fn ($row) => array_keys($row->toArray()) === ['employeeId', 'name', 'positionName', 'state', 'stateLabel', 'level', 'levelLabel', 'signedOn', 'assessmentId']));
    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->center->id, 'q' => 'Ana Ruiz']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 1)->where('people.data.0.name', 'Ana Ruiz')->where('people.data.0.state', 'signed'));
});

// R2-compliance-07: asking people to confirm needs signed assessments to confirm.
it('says how many assessments of the center are signed, so asking for confirmations is offered only when there are', function (): void {
    $this->actingAs($this->cora);
    $url = route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('signed', 0));

    RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('signed', 1));
});

// Rediseño v2: the assessment is entered from the route of its center, so it names the center for its breadcrumbs.
it('names the center of the assessment', function (): void {
    $draft = RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);

    $this->actingAs($this->cora)->get(route('tenant.seating.assessments.show', [$this->tenant, $draft->id]))
        ->assertInertia(fn (Assert $page) => $page->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Norte']));
});
