<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

const HEALTH_SECRETS = ['NOTA-SALUD-SECRETA', 'OTROS-ASPECTOS-SECRETOS', 'NOTA-CANALIZACION-SECRETA', 'DESCRIPCION-REPORTE-SECRETA'];

/**
 * Ley Silla handles health data and a confidential survey. Whatever the flow, no log line and no audit entry may carry what
 * a person said about their health, the notes about it, the scores of an assessment or an answer of the survey, nor link the
 * person to it (criteria C-18 and C-19).
 */
beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    Bus::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    staffOf($this->tenant, $this->center, 2);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
    $this->logged = [];
    Log::listen(function (MessageLogged $event): void {
        $this->logged[] = json_encode([$event->message, $event->context]);
    });
});

/** Runs the whole path of a person's health data and the survey, as the people involved would. */
function runHealthFlows(object $test): void
{
    $test->post(route('portal.seating.inputs.store', $test->token), ['footwear' => 3, 'discomfort' => 3, 'note' => 'NOTA-SALUD-SECRETA', 'accepted' => '1'])->assertSessionHasNoErrors();
    $draft = RiskAssessment::factory()->create([
        'tenant_id' => $test->tenant->id, 'employee_id' => $test->ana->id, 'position_id' => $test->position->id, 'work_center_id' => $test->center->id,
        'scores' => ['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'footwear' => 3, 'movement_space' => 2, 'discomfort' => 3, 'pauses' => 2],
        'other_aspects' => 'OTROS-ASPECTOS-SECRETOS', 'verified_on' => '2026-02-27', 'person_provided_health_data' => true,
    ]);
    $test->actingAs($test->cora)->post(route('tenant.seating.assessments.sign', [$test->tenant, $draft->id]))->assertSessionHasNoErrors();
    $test->actingAs($test->hr)->post(route('tenant.seating.referrals.store', [$test->tenant, $draft->id]), ['referred' => true, 'referred_on' => '2026-03-01', 'note' => 'NOTA-CANALIZACION-SECRETA'])->assertSessionHasNoErrors();
    $test->post(route('portal.seating.acknowledgments.store', $test->token))->assertSessionHasNoErrors();
    $test->post(route('portal.seating.reports.store', $test->token), ['description' => 'DESCRIPCION-REPORTE-SECRETA'])->assertSessionHasNoErrors();
    $test->actingAs($test->hr)->post(route('tenant.seating.survey.launch', [$test->tenant, $test->center->id]), ['closes_on' => '2026-03-12'])->assertSessionHasNoErrors();
    $participant = Participant::query()->where('employee_id', $test->ana->id)->sole();
    $participant->forceFill(['token_hash' => Participant::hashToken('survey-token')])->save();
    $test->post(route('survey.submit', ['survey-token', 'seating.survey']), ['answers' => surveyAnswers('no')])->assertSessionHasNoErrors();
}

it('writes no health data, note, score or survey answer to any log line', function (): void {
    runHealthFlows($this);

    $log = implode("\n", $this->logged);

    expect($this->logged)->not->toBe([]);
    foreach (HEALTH_SECRETS as $secret) {
        expect($log)->not->toContain($secret);
    }

    expect($log)->not->toContain('Ana Ruiz')->not->toContain('"answers"')->not->toContain('"discomfort"')->not->toContain('"footwear"')->not->toContain('"scores"');
});

it('writes no health data, note, score or survey answer to the audit trail', function (): void {
    runHealthFlows($this);

    $audit = AuditEntry::query()->get()->map(fn (AuditEntry $entry): string => (string) json_encode([$entry->event, $entry->metadata]))->implode("\n");

    expect($audit)->toContain('seating.')
        ->and($audit)->not->toContain('Ana Ruiz')->not->toContain('"answers"')->not->toContain('"discomfort"')->not->toContain('"footwear"')->not->toContain('"scores"');
    foreach (HEALTH_SECRETS as $secret) {
        expect($audit)->not->toContain($secret);
    }
});

it('keeps the tables of the survey and of the seat reports without a key to a person, an invitation or a token', function (): void {
    $columns = fn (string $table): array => collect(DB::select('SELECT column_name FROM information_schema.columns WHERE table_name = ?', [$table]))->pluck('column_name')->all();
    $references = fn (string $table): array => collect(DB::select(<<<'SQL'
        SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
        JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
        WHERE source.relname = ? AND c.contype = 'f'
    SQL, [$table]))->pluck('referenced')->all();

    foreach (['anonymous_responses', 'seat_reports'] as $table) {
        expect($columns($table))->not->toContain('employee_id')->not->toContain('participant_id')->not->toContain('token')->not->toContain('token_hash')->not->toContain('invitation_id')
            ->and($references($table))->not->toContain('employees')->not->toContain('campaign_participants')->not->toContain('participants');
    }
});
