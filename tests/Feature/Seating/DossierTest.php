<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Http\Controllers\DossierController;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\Dossier;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Dossier::class, DossierController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'legal_name' => 'Acme Manufactura SA de CV']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create($this->where + ['user_id' => $this->cora->id]);
    $this->url = route('tenant.seating.dossier', [$this->tenant, $this->center->id]);
});

/** One person in the center with a signed assessment of the given level, and a secret in every private field. */
function assessedPerson(object $test, string $name, RiskLevel $level): Employee
{
    $position = StandingPosition::query()->first() ?? StandingPosition::factory()->nearby()->create($test->where);
    $person = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->center->id, 'name' => $name]);
    PositionAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $person->id, 'position_id' => $position->id]);
    RiskAssessment::factory()->signed()->create($test->where + [
        'employee_id' => $person->id, 'position_id' => $position->id, 'level' => $level, 'total' => 10, 'other_aspects' => 'OTROS-ASPECTOS-PRIVADOS',
        'scores' => array_fill_keys(['standing_time', 'posture_change', 'surface', 'footwear', 'movement_space', 'discomfort', 'pauses'], 2),
    ]);

    return $person;
}

it('prints one section per requirement, with what exists and its date, or «Falta»', function (): void {
    $this->actingAs($this->hr)->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('seating/dossier')
        ->where('header.workCenter', 'Planta Norte')
        ->where('header.legalName', 'Acme Manufactura SA de CV')
        ->where('header.generatedOn', '2026-11-02')
        ->where('rows', fn ($rows) => collect($rows)->pluck('key')->all() === array_map(fn (int $number): string => "r{$number}", range(1, 13)))
        ->where('rows.11.state', 'missing')
        ->where('rows.11.stateLabel', 'Falta')
        ->where('rows.11.lines', [['text' => 'Falta', 'date' => null]]));
});

it('prints the date, the folio and the state of each piece of evidence that exists', function (): void {
    SeatingProfile::factory()->create($this->where + ['sector' => 'services', 'declared_on' => '2026-10-02']);
    InternalRegulation::factory()->distributed()->create($this->where);
    SeatingDocument::factory()->create($this->where + ['kind' => 'diagnosis_program', 'title' => 'Diagnóstico 2026', 'document_on' => '2026-09-15', 'incisos' => ['B', 'C']]);
    $position = StandingPosition::factory()->nearby()->create($this->where + ['name' => 'Cajera']);
    PauseAgreement::factory()->create($this->where + ['position_id' => $position->id, 'agreed_on' => '2026-10-10', 'pauses_per_shift' => 3, 'minutes_each' => 10]);
    PreventiveMeasure::factory()->create($this->where + ['position_id' => $position->id, 'due_on' => '2026-12-01']);

    $rows = collect($this->actingAs($this->hr)->get($this->url)->viewData('page')['props']['rows'])->keyBy('key');
    $texts = fn (string $key): array => collect($rows[$key]['lines'])->pluck('text')->all();

    expect($rows['r1']['lines'][0])->toBe(['text' => 'Sector declarado: Servicios', 'date' => '2026-10-02'])
        ->and($texts('r1'))->toContain('Cajera: resultado B')
        ->and($texts('r10'))->toContain('Folio del depósito: CFCRL-2026-0001')
        ->and(collect($rows['r10']['lines'])->pluck('date')->filter()->values()->all())->toBe(['2026-08-02', '2026-09-02', '2026-10-02'])
        ->and($texts('r11'))->toContain('Cajera: 3 pausas de 10 minutos por turno')
        ->and($rows['r11']['lines'][0]['date'])->toBe('2026-10-10')
        ->and($texts('r12'))->toContain('Diagnóstico 2026 (incisos B, C)')
        ->and($rows['r12']['lines'][0]['date'])->toBe('2026-09-15')
        ->and($rows['r12']['state'])->toBe('met')
        ->and($texts('r7'))->toContain('Programa de pausas activas: Planeada')
        ->and($rows['r7']['lines'][0]['date'])->toBe('2026-12-01');
});

it('prints the level distribution without names, and lists assessments, photos, referrals and answers only as links', function (): void {
    $ana = assessedPerson($this, 'Ana Ruiz', RiskLevel::Low);
    assessedPerson($this, 'Beto Soto', RiskLevel::Medium);
    assessedPerson($this, 'Carla Paz', RiskLevel::High);
    assessedPerson($this, 'Dora Gil', RiskLevel::High);
    Seat::factory()->create($this->where + ['photo_path' => 'seats/FOTO-PRIVADA.jpg']);
    MedicalReferral::factory()->create($this->where + ['employee_id' => $ana->id, 'note' => 'NOTA-CLINICA']);

    $props = $this->actingAs($this->owner)->get($this->url)->assertOk()->viewData('page')['props'];
    $json = (string) json_encode($props);

    expect($props['levels'])->toBe(['low' => 1, 'medium' => 1, 'high' => 2])
        ->and($props['links'])->toBe([
            ['key' => 'people', 'label' => 'Evaluaciones por persona', 'count' => 4, 'open' => true],
            ['key' => 'inventory', 'label' => 'Fotos de los asientos', 'count' => 1, 'open' => true],
            ['key' => 'people', 'label' => 'Canalizaciones', 'count' => 1, 'open' => true],
            ['key' => 'survey', 'label' => 'Respuestas de la encuesta', 'count' => 0, 'open' => true],
        ]);

    foreach (['Ana Ruiz', 'Beto Soto', 'Carla Paz', 'Dora Gil', 'OTROS-ASPECTOS-PRIVADOS', 'FOTO-PRIVADA', 'NOTA-CLINICA', 'discomfort', 'scores', 'health_note'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

it('does not offer the survey answers to who cannot open them', function (): void {
    $this->actingAs($this->cora)->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('links', fn ($links) => collect($links)->firstWhere('key', 'survey')['open'] === false));
});

it('states that the law sets no fine of its own and the range per affected person, without naming a fraction', function (): void {
    $note = $this->actingAs($this->hr)->get($this->url)->viewData('page')['props']['fineNote'];

    expect($note)->toContain('no fija una multa propia')->toContain('50 a 5,000 UMA por persona afectada')->toContain('se duplican si reinciden')
        ->and($note)->not->toMatch('/fracci|994|1002|992/');

    config(['seating.fine_uma_min' => 60, 'seating.fine_uma_max' => 6000]);
    expect($this->actingAs($this->hr)->get($this->url)->viewData('page')['props']['fineNote'])->toContain('60 a 6,000 UMA');
});

it('audits and logs every view with ids only', function (): void {
    Log::spy();

    $this->actingAs($this->hr)->get($this->url)->assertOk();
    $this->actingAs($this->cora)->get($this->url)->assertOk();

    expect(AuditEntry::query()->where('event', 'seating.dossier_viewed')->count())->toBe(2)
        ->and(AuditEntry::query()->where('event', 'seating.dossier_viewed')->first()->metadata)->toEqual(['work_center_id' => $this->center->id]);
    Log::shouldHaveReceived('info')->with('seating.dossier_viewed', ['work_center_id' => $this->center->id])->twice();
});

it('lets owner, hr and the committee of the center read it, and answers 403 to the viewer', function (): void {
    foreach (['owner', 'hr', 'cora'] as $who) {
        $this->actingAs($this->{$who})->get($this->url)->assertOk();
    }

    $this->actingAs($this->viewer)->get($this->url)->assertForbidden();
    expect(AuditEntry::query()->where('event', 'seating.dossier_viewed')->count())->toBe(3);
});

it('answers 404 for a center the committee does not sit on, another company, an archived or a made-up center', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create(['slug' => 'other']))->create();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    $this->actingAs($this->cora)->get(route('tenant.seating.dossier', [$this->tenant, $this->other->id]))->assertNotFound();
    $this->actingAs($this->owner);
    $this->get(route('tenant.seating.dossier', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.seating.dossier', [$this->tenant, $archived->id]))->assertNotFound();
    $this->get(route('tenant.seating.dossier', [$this->tenant, (string) Illuminate\Support\Str::uuid()]))->assertNotFound();
});

it('asks the same queries however many positions, people and seats there are', function (): void {
    $this->actingAs($this->hr)->get($this->url)->assertOk();
    DB::enableQueryLog();
    assessedPerson($this, 'Ana Ruiz', RiskLevel::Low);
    Seat::factory()->create($this->where);
    DB::flushQueryLog();
    $this->get($this->url)->assertOk();
    $small = count(DB::getQueryLog());

    foreach (range(1, 5) as $index) {
        assessedPerson($this, "Persona {$index}", RiskLevel::Medium);
        StandingPosition::factory()->create($this->where);
        Seat::factory()->create($this->where);
        PauseAgreement::factory()->create($this->where + ['position_id' => StandingPosition::factory()->create($this->where)->id]);
        SeatingDocument::factory()->create($this->where);
    }

    DB::flushQueryLog();
    $this->get($this->url)->assertOk();

    expect(count(DB::getQueryLog()))->toBe($small);
});

it('counts the signed assessments that have their minutes, in the right number', function (): void {
    assessedPerson($this, 'Ana Ruiz', RiskLevel::Low);
    $texts = fn () => collect($this->actingAs($this->hr)->get($this->url)->viewData('page')['props']['rows'])->firstWhere('key', 'r3')['lines'][0]['text'];

    expect($texts())->toBe('0 de 1 evaluación firmada con su acta');

    assessedPerson($this, 'Beto Soto', RiskLevel::Low);
    expect($texts())->toBe('0 de 2 evaluaciones firmadas con su acta');
});

// Board V3-Inspeccion: «Solo lo que tienes», to hand to the inspector.
it('keeps only the requirements the center can show when asked for what it has', function (): void {
    $this->actingAs($this->hr)->get($this->url.'?ready=1')->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->every(fn (array $row): bool => $row['state'] === 'met') && collect($rows)->pluck('key')->doesntContain('r3')));
    $this->actingAs($this->hr)->get($this->url)->assertInertia(fn (Assert $page) => $page->has('rows', 13));
});
