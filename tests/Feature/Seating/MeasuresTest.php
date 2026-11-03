<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\CancelMeasure;
use App\Modules\Seating\Actions\ProposeMeasure;
use App\Modules\Seating\Actions\SaveMeasure;
use App\Modules\Seating\Http\Controllers\MeasureController;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProposeMeasure::class, SaveMeasure::class, CancelMeasure::class, MeasureController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.seating_files'));
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->assessment = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function evidencePdf(string $name = 'evidencia.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

it('lets the committee propose a measure from an assessment, but not manage it', function (): void {
    $this->actingAs($this->cora);

    $this->post(route('tenant.seating.measures.propose', [$this->tenant, $this->assessment->id]), ['kind' => 'task_rotation'])->assertRedirect()->assertSessionHasNoErrors();

    $measure = PreventiveMeasure::query()->sole();
    expect($measure->kind->value)->toBe('task_rotation')->and($measure->status->value)->toBe('planned')
        ->and($measure->assessment_id)->toBe($this->assessment->id)->and($measure->position_id)->toBe($this->position->id)
        ->and($measure->work_center_id)->toBe($this->center->id)->and($measure->proposed_by)->toBe($this->cora->id);

    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done'])->assertForbidden();
    $this->post(route('tenant.seating.measures.cancel', [$this->tenant, $measure->id]))->assertForbidden();
    $this->post(route('tenant.seating.measures.store', [$this->tenant, $this->center->id]), ['kind' => 'other', 'note' => 'x'])->assertForbidden();
    expect(PreventiveMeasure::query()->sole()->status->value)->toBe('planned');
});

it('refuses the proposal to the viewer, to an owner not assigned, and hides other centers from the committee', function (): void {
    $url = route('tenant.seating.measures.propose', [$this->tenant, $this->assessment->id]);
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $elsewhere->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post($url, ['kind' => 'active_breaks'])->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))->post($url, ['kind' => 'active_breaks'])->assertForbidden();
    $this->actingAs($elsewhere)->post($url, ['kind' => 'active_breaks'])->assertNotFound();

    expect(PreventiveMeasure::query()->count())->toBe(0);
});

it('needs a note for «other» and a known kind', function (array $bad, string $field): void {
    $this->actingAs($this->cora);

    $this->post(route('tenant.seating.measures.propose', [$this->tenant, $this->assessment->id]), $bad)->assertSessionHasErrors($field);

    expect(PreventiveMeasure::query()->count())->toBe(0);
})->with([
    'other without note' => [['kind' => 'other', 'note' => ' '], 'note'],
    'unknown kind' => [['kind' => 'magic'], 'kind'],
    'no kind' => [[], 'kind'],
]);

it('lets RH register a measure for a position or an assessment, with owner, due date and status', function (): void {
    Log::spy();
    $owner = memberOf($this->tenant, TenantRole::Owner);
    $this->actingAs($this->hr);

    $this->post(route('tenant.seating.measures.store', [$this->tenant, $this->center->id]), [
        'kind' => 'cushioned_floor', 'position_id' => $this->position->id, 'owner_user_id' => $owner->id, 'due_on' => '2026-04-15', 'status' => 'in_progress',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $measure = PreventiveMeasure::query()->sole();
    expect($measure->position_id)->toBe($this->position->id)->and($measure->assessment_id)->toBeNull()->and($measure->owner_user_id)->toBe($owner->id)
        ->and($measure->due_on?->toDateString())->toBe('2026-04-15')->and($measure->status->value)->toBe('in_progress');
    Log::shouldHaveReceived('info')->with('seating.measure_saved', ['measure_id' => $measure->id, 'work_center_id' => $this->center->id, 'kind' => 'cushioned_floor', 'status' => 'in_progress'])->once();
});

it('refuses a measure tied to nothing, to another center or with an owner from another company', function (array $bad, string $field): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $foreignPosition = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id]);
    $stranger = memberOf(Tenant::factory()->create(), TenantRole::Owner);
    $this->actingAs($this->hr);
    $bad = array_map(fn ($value) => $value === 'FOREIGN_POSITION' ? $foreignPosition->id : ($value === 'STRANGER' ? $stranger->id : $value), $bad);

    $this->post(route('tenant.seating.measures.store', [$this->tenant, $this->center->id]), ['kind' => 'active_breaks', ...$bad])->assertSessionHasErrors($field);

    expect(PreventiveMeasure::query()->count())->toBe(0);
})->with([
    'tied to nothing' => [[], 'position_id'],
    'a position of another center' => [['position_id' => 'FOREIGN_POSITION'], 'position_id'],
    'an owner of another company' => [['position_id' => null, 'owner_user_id' => 'STRANGER'], 'owner_user_id'],
]);

it('lets RH manage the status, the owner, the due date and the evidence, replacing the previous file', function (): void {
    $measure = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $owner = memberOf($this->tenant, TenantRole::Owner);
    $this->actingAs($this->hr);

    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done', 'owner_user_id' => $owner->id, 'due_on' => '2026-05-01', 'evidence' => evidencePdf()])->assertRedirect()->assertSessionHasNoErrors();

    $first = PreventiveMeasure::query()->sole();
    expect($first->status->value)->toBe('done')->and($first->owner_user_id)->toBe($owner->id)->and($first->evidence_path)->toEndWith('.pdf');
    Storage::disk(config()->string('filesystems.seating_files'))->assertExists((string) $first->evidence_path);

    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done', 'evidence' => UploadedFile::fake()->image('foto.jpg', 30, 20)])->assertSessionHasNoErrors();

    $second = PreventiveMeasure::query()->sole();
    expect($second->evidence_path)->toEndWith('.jpg')->and($second->evidence_path)->not->toBe($first->evidence_path);
    Storage::disk(config()->string('filesystems.seating_files'))->assertMissing((string) $first->evidence_path);
});

it('takes only a PDF or a JPG/PNG image as evidence, by content', function (): void {
    $measure = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $this->actingAs($this->hr);

    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done', 'evidence' => UploadedFile::fake()->createWithContent('x.pdf', 'texto plano')])->assertSessionHasErrors('evidence');
    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done', 'evidence' => UploadedFile::fake()->createWithContent('x.png', 'texto plano')])->assertSessionHasErrors('evidence');

    expect(PreventiveMeasure::query()->sole()->evidence_path)->toBeNull();
});

it('serves the evidence only to RH and the Comisión of the center, never cached', function (): void {
    $measure = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $this->actingAs($this->hr)->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done', 'evidence' => evidencePdf()]);
    $url = route('tenant.seating.measures.evidence', [$this->tenant, $measure->id]);

    $response = $this->actingAs($this->cora)->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
    $without = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $this->actingAs($this->hr)->get(route('tenant.seating.measures.evidence', [$this->tenant, $without->id]))->assertNotFound();
});

it('cancels a measure instead of deleting it, and a cancelled one is not edited', function (): void {
    $measure = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $this->actingAs($this->hr);

    $this->post(route('tenant.seating.measures.cancel', [$this->tenant, $measure->id]))->assertRedirect();

    expect(PreventiveMeasure::query()->sole()->status->value)->toBe('cancelled');
    $this->put(route('tenant.seating.measures.update', [$this->tenant, $measure->id]), ['status' => 'done'])->assertSessionHasErrors('status');
    expect(PreventiveMeasure::query()->sole()->status->value)->toBe('cancelled');
});

it('has no destroy route for measures', function (): void {
    $deletes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true) && str_contains((string) $route->getName(), 'seating.measures'));

    expect($deletes)->toHaveCount(0);
});

it('shows the plan to RH and the Comisión of the center, with no names, and not to the viewer', function (): void {
    PreventiveMeasure::factory()->count(17)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $url = route('tenant.seating.plan', [$this->tenant, $this->center->id]);

    $this->actingAs($this->cora)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('seating/plan')
        ->where('measures.total', 17)->where('measures.per_page', 15)->has('measures.data', 15)->where('canManage', false)
        ->where('measures.data.0', fn ($row) => array_keys($row->toArray()) === ['id', 'kindLabel', 'note', 'status', 'statusLabel', 'positionName', 'ownerName', 'dueOn', 'overdue', 'hasEvidence', 'fromAssessment', 'evidenceHref']));
    $this->get(route('tenant.seating.plan', [$this->tenant, $this->center->id, 'measures' => 2]))->assertInertia(fn (Assert $page) => $page->has('measures.data', 2));
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($url)->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))->get($url)->assertInertia(fn (Assert $page) => $page->where('canManage', true));
});

it('flags a measure past its due date that is not done or cancelled', function (string $status, bool $overdue): void {
    PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id, 'status' => $status, 'due_on' => '2026-02-28']);

    $this->actingAs($this->hr)->get(route('tenant.seating.plan', [$this->tenant, $this->center->id]))->assertInertia(fn (Assert $page) => $page->where('measures.data.0.overdue', $overdue));
})->with([['planned', true], ['in_progress', true], ['done', false], ['cancelled', false]]);

it('does not touch the measures of another company', function (): void {
    $foreign = PreventiveMeasure::factory()->create();
    $this->actingAs($this->hr);

    $this->put(route('tenant.seating.measures.update', [$this->tenant, $foreign->id]), ['status' => 'done'])->assertNotFound();
    $this->post(route('tenant.seating.measures.cancel', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.seating.measures.evidence', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.seating.plan', [$this->tenant, $foreign->work_center_id]))->assertNotFound();
});

it('tells the person, when they confirm, the measures that apply to them and not the cancelled ones', function (): void {
    foreach ([['task_rotation', 'planned'], ['ergonomic_footwear', 'cancelled']] as [$kind, $status]) {
        PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id, 'assessment_id' => $this->assessment->id, 'kind' => $kind, 'status' => $status]);
    }
    PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id, 'kind' => 'cushioned_floor']);
    PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id, 'kind' => 'active_breaks']);
    $token = resolve(IssuePortalLink::class)->handle($this->ana->id);

    $this->get(route('portal.seating.show', $token))->assertInertia(fn (Assert $page) => $page->where('acknowledgment.measures', fn ($measures) => collect($measures)->sort()->values()->all() === collect(['Acondicionar el piso con superficie amortiguada', 'Alternar tareas para cambiar de postura'])->sort()->values()->all()));
});

it('refuses in the database a measure with an unknown kind or status, or tied to nothing', function (): void {
    $measure = PreventiveMeasure::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id]);
    $attempts = [
        'preventive_measures_kind_known' => fn () => DB::table('preventive_measures')->update(['kind' => 'magic']),
        'preventive_measures_status_known' => fn () => DB::table('preventive_measures')->update(['status' => 'lost']),
        'preventive_measures_tied_to_something' => fn () => DB::table('preventive_measures')->update(['position_id' => null, 'assessment_id' => null]),
    ];

    foreach ($attempts as $constraint => $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
    expect($measure->fresh()?->kind->value)->not->toBe('magic');
});
