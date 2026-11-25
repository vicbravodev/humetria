<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\Readers\AttendanceReader;
use App\Modules\Assistant\Support\Readers\ClimateReader;
use App\Modules\Assistant\Support\Readers\CompensationReader;
use App\Modules\Assistant\Support\Readers\ComplaintsReader;
use App\Modules\Assistant\Support\Readers\DisclosureReader;
use App\Modules\Assistant\Support\Readers\OrgChartReader;
use App\Modules\Assistant\Support\Readers\PulseReader;
use App\Modules\Assistant\Support\Readers\SeatingReader;
use App\Modules\Assistant\Support\Readers\VacationsReader;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

mutates(AttendanceReader::class, CompensationReader::class, DisclosureReader::class, OrgChartReader::class, SeatingReader::class, VacationsReader::class, ComplaintsReader::class, PulseReader::class, ClimateReader::class, App\Modules\Assistant\Support\Readers\SurveyLine::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
});

/** The line of one module in the compliance overview, or null when Humi does not tell it. */
function overviewLine(object $test, string $key): ?array
{
    return array_values(array_filter(toolCard(GetComplianceOverview::class, $test->scope)['rows'], fn (array $row): bool => $row['key'] === $key))[0] ?? null;
}

/** A center of `$tenant` with a standing position held by one person, its Comisión sitting and nothing else done. */
function standingPlantOf(Tenant $tenant): WorkCenter
{
    $center = WorkCenter::factory()->for($tenant)->create();
    $where = ['tenant_id' => $tenant->id, 'work_center_id' => $center->id];
    SeatingProfile::factory()->create($where);
    $position = StandingPosition::factory()->create($where);
    $person = Employee::factory()->create($where);
    PositionAssignment::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $person->id, 'position_id' => $position->id]);
    CommitteeAssignment::factory()->create($where + ['user_id' => memberOf($tenant, TenantRole::Committee)->id]);

    return $center;
}

/** The Ley Silla route answers `$progress` for the centers of the company, in their order. */
function seatingRouteAnswers(Tenant $tenant, RouteProgress ...$progress): void
{
    $ids = array_map(fn (): string => WorkCenter::factory()->for($tenant)->create()->id, $progress);
    app()->instance(SeatingRoute::class, new readonly class(array_combine($ids, $progress)) implements SeatingRoute
    {
        /** @param array<string, RouteProgress> $answer */
        public function __construct(private array $answer) {}

        public function progress(array $workCenterIds): array
        {
            return array_intersect_key($this->answer, array_flip($workCenterIds));
        }
    });
}

function humiRouteOf(int $done): RouteProgress
{
    return new RouteProgress(true, array_map(fn (int $stage): bool => $stage < $done, range(0, RouteProgress::STAGES - 1)), 'sigue');
}

it('tells Ley Silla does not apply while nobody works standing in the centers of the company', function (): void {
    $offices = WorkCenter::factory()->for($this->a)->create();
    SeatingProfile::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $offices->id, 'no_standing_positions' => true]);

    expect(overviewLine($this, 'seating'))->toBe([
        'key' => 'seating',
        'label' => 'Ley Silla',
        'state' => 'not_applicable',
        'stateLabel' => 'No aplica',
        'summary' => 'Nadie trabaja de pie en tus centros.',
        'link' => "/admin/{$this->a->slug}/seating",
    ]);
});

it('counts the stages of the route done in the centers where someone works standing, never those of another company', function (): void {
    standingPlantOf($this->a);
    standingPlantOf($this->b);
    standingPlantOf($this->b);
    workIn($this->a);

    expect(overviewLine($this, 'seating'))->toMatchArray([
        'state' => 'in_progress',
        'stateLabel' => 'En curso',
        'summary' => 'Cumples 1 de 6 etapas de la ruta en 1 centro con puestos de pie.',
    ]);
});

it('leaves out the archived centers', function (): void {
    standingPlantOf($this->a)->update(['archived_at' => now()]);
    workIn($this->a);

    expect(overviewLine($this, 'seating')['state'])->toBe('not_applicable');
});

it('puts Ley Silla up to date when every center finished its route', function (): void {
    seatingRouteAnswers($this->a, humiRouteOf(6), humiRouteOf(6), RouteProgress::notApplicable('Nadie trabaja de pie'));

    expect(overviewLine($this, 'seating'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'Cumples 12 de 12 etapas de la ruta en 2 centros con puestos de pie.',
    ]);
});

it('puts Ley Silla pending while a center has not started its route', function (): void {
    seatingRouteAnswers($this->a, humiRouteOf(6), humiRouteOf(0));

    expect(overviewLine($this, 'seating'))->toMatchArray(['state' => 'pending', 'stateLabel' => 'Pendiente']);
});

it('puts Ley Silla pending while a center has not said whether it applies there', function (): void {
    seatingRouteAnswers($this->a, humiRouteOf(6), RouteProgress::unanswered(), RouteProgress::unanswered());

    expect(overviewLine($this, 'seating'))->toMatchArray([
        'state' => 'pending',
        'summary' => 'Cumples 6 de 6 etapas de la ruta en 1 centro con puestos de pie. Falta contestar si aplica en 2 centros.',
    ]);
});

it('puts Ley Silla pending when no center has said yet whether it applies there', function (): void {
    seatingRouteAnswers($this->a, RouteProgress::unanswered());

    expect(overviewLine($this, 'seating'))->toMatchArray(['state' => 'pending', 'summary' => 'Falta contestar si aplica en 1 centro.']);
});

it('does not tell Ley Silla to a company without it, and offers the overview to a company with only Ley Silla', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'seating'))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, ['seating'], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_column(toolCard(GetComplianceOverview::class, $this->scope)['rows'], 'key'))->toBe(['seating', 'access'])
        ->and(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
});

it('tells vacations are up to date while no request waits for RH', function (): void {
    // Still with the manager or already answered: none of them waits for RH.
    LeaveRequest::factory()->create(['tenant_id' => $this->a->id]);
    LeaveRequest::factory()->approved()->escalated()->create(['tenant_id' => $this->a->id]);

    expect(overviewLine($this, 'vacations'))->toBe([
        'key' => 'vacations',
        'label' => 'Vacaciones',
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'No hay solicitudes de vacaciones por atender.',
        'link' => "/admin/{$this->a->slug}/vacations/requests",
    ]);
});

it('counts the vacation requests that wait for RH, never those of another company nor who asked', function (): void {
    LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->a->id]);
    LeaveRequest::factory()->escalated()->count(3)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    expect(overviewLine($this, 'vacations'))->toMatchArray(['state' => 'pending', 'stateLabel' => 'Pendiente', 'summary' => 'Hay 1 solicitud de vacaciones por atender.']);

    LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->a->id]);

    expect(overviewLine($this, 'vacations')['summary'])->toBe('Hay 2 solicitudes de vacaciones por atender.');
});

it('does not tell vacations to a company without them, and offers the overview to a company with only vacations', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'vacations'))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
});

/** A complaint of `$tenant` that waits for `$userId` to acknowledge it, received on `$receivedOn`. */
function complaintWaitingFor(Tenant $tenant, ComplaintChannel $channel, int $userId, string $receivedOn): void
{
    $complaint = Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'received_on' => $receivedOn, 'description' => 'Mi jefe Zafiro me grita']);
    ComplaintAssignment::factory()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $userId]);
}

it('tells whoever handles complaints how many wait for their acknowledgment and when the first falls due, never what they say', function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $channel = ComplaintChannel::factory()->forTenant($this->a->id)->active()->create(['acknowledge_within_days' => 3]);
    complaintWaitingFor($this->a, $channel, $this->scope->userId, '2026-11-19');
    complaintWaitingFor($this->a, $channel, $this->scope->userId, '2026-11-18');
    // Someone else's, and one of another company held by the same person: neither is theirs here.
    complaintWaitingFor($this->a, $channel, memberOf($this->a, TenantRole::Owner)->id, '2026-11-10');
    complaintWaitingFor($this->b, ComplaintChannel::factory()->forTenant($this->b->id)->active()->create(), $this->scope->userId, '2026-11-10');
    workIn($this->a);

    $line = overviewLine($this, 'complaints');

    expect($line)->toBe([
        'key' => 'complaints',
        'label' => 'Quejas',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Tienes 2 casos por acusar de recibo; el primero vence el 21 de noviembre.',
        'link' => "/admin/{$this->a->slug}/complaints",
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro');
});

it('tells whoever handles complaints they are up to date with none waiting, and that a channel not open takes nothing', function (): void {
    $channel = ComplaintChannel::factory()->forTenant($this->a->id)->active()->create();

    expect(overviewLine($this, 'complaints'))->toMatchArray(['state' => 'up_to_date', 'stateLabel' => 'Al día', 'summary' => 'No tienes casos por acusar de recibo.']);

    $channel->delete();

    expect(overviewLine($this, 'complaints'))->toMatchArray(['state' => 'pending', 'summary' => 'Tu canal de quejas aún no recibe casos.']);
});

it('tells someone who does not handle complaints only whether the channel takes them, never how many there are', function (): void {
    $channel = ComplaintChannel::factory()->forTenant($this->a->id)->active()->create();
    $viewer = memberOf($this->a, TenantRole::Viewer);
    complaintWaitingFor($this->a, $channel, $viewer->id, now()->toDateString());
    workIn($this->a);
    $this->scope = resolve(App\Modules\Assistant\Support\AssistantScopeFactory::class)->make($viewer);

    expect(overviewLine($this, 'complaints'))->toBe([
        'key' => 'complaints',
        'label' => 'Quejas',
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'Tu canal de quejas recibe casos; cuántos hay solo lo ven quienes los atienden.',
        'link' => "/admin/{$this->a->slug}",
    ]);
});

it('does not tell complaints to a company without them, and offers the overview to a company with only complaints', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'complaints'))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, ['complaints'], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
});

/** `$answered` people of the company of `$survey` answered it, and one more was invited and did not. */
function humiSurveyAnsweredBy(Survey $survey, int $answered): Survey
{
    Invitation::factory()->answered()->count($answered)->create(['survey_id' => $survey->id]);
    Invitation::factory()->create(['survey_id' => $survey->id]);

    return $survey;
}

it('tells no pulse nor climate survey was launched yet', function (): void {
    expect(overviewLine($this, 'pulse'))->toBe([
        'key' => 'pulse',
        'label' => 'Pulso',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no has lanzado ninguna encuesta de pulso.',
        'link' => "/admin/{$this->a->slug}/pulse",
    ])->and(overviewLine($this, 'climate'))->toBe([
        'key' => 'climate',
        'label' => 'Clima',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no has lanzado ninguna encuesta de clima.',
        'link' => "/admin/{$this->a->slug}/climate",
    ]);
});

it('tells a draft waits to be launched', function (): void {
    Survey::factory()->pulse()->create(['tenant_id' => $this->a->id]);
    workIn($this->a);

    expect(overviewLine($this, 'pulse'))->toMatchArray(['state' => 'pending', 'summary' => 'Tienes una encuesta de pulso en borrador, sin lanzar.']);
});

it('tells until when the open survey takes answers and how many answered, never of another company nor what they said', function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $survey = humiSurveyAnsweredBy(Survey::factory()->active('2026-11-30')->create(['tenant_id' => $this->a->id, 'invited' => 80]), 34);
    Comment::factory()->create(['tenant_id' => $this->a->id, 'survey_id' => $survey->id, 'body' => 'Mi jefe Zafiro no escucha']);
    humiSurveyAnsweredBy(Survey::factory()->active('2026-12-15')->create(['tenant_id' => $this->b->id, 'invited' => 9]), 6);
    workIn($this->a);

    $line = overviewLine($this, 'climate');

    expect($line)->toMatchArray([
        'state' => 'in_progress',
        'stateLabel' => 'En curso',
        'summary' => 'La encuesta de clima está abierta hasta el 30 de noviembre: han contestado 34 de 80 personas.',
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro');

    Invitation::query()->where('answered', true)->limit(33)->delete();

    expect(overviewLine($this, 'climate')['summary'])->toBe('La encuesta de clima está abierta hasta el 30 de noviembre: ha contestado 1 de 80 personas.');
});

it('tells the results of the last closed survey are ready, together with the one open', function (): void {
    humiSurveyAnsweredBy(Survey::factory()->pulse()->closed()->create(['tenant_id' => $this->a->id, 'closed_at' => '2026-11-10 18:00:00']), 5);
    humiSurveyAnsweredBy(Survey::factory()->pulse()->active('2026-11-30')->create(['tenant_id' => $this->a->id, 'invited' => 12]), 0);
    workIn($this->a);

    expect(overviewLine($this, 'pulse'))->toMatchArray([
        'state' => 'in_progress',
        'summary' => 'La encuesta de pulso está abierta hasta el 30 de noviembre: han contestado 0 de 12 personas. La última cerró el 10 de noviembre y sus resultados están listos.',
    ]);
});

it('puts the survey up to date once the last one closed, and says its results are not shown below the minimum of respondents', function (): void {
    humiSurveyAnsweredBy(Survey::factory()->closed()->create(['tenant_id' => $this->a->id, 'closed_at' => '2026-11-10 18:00:00']), 4);
    workIn($this->a);

    expect(overviewLine($this, 'climate'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'La última encuesta de clima cerró el 10 de noviembre con menos de 5 respuestas: sus resultados no se muestran.',
    ]);
});

it('does not tell pulse nor climate to a company without them, and offers the overview to a company with only one of them', function (string $module): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, $module))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, [$module], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_column(toolCard(GetComplianceOverview::class, $this->scope)['rows'], 'key'))->toBe([$module, 'access'])
        ->and(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
})->with(['pulse', 'climate']);

it('tells a company that has not started with attendance to review its policy', function (): void {
    expect(overviewLine($this, 'attendance'))->toBe([
        'key' => 'attendance',
        'label' => 'Asistencia',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no revisas la política del registro de jornada.',
        'link' => "/admin/{$this->a->slug}/attendance/setup",
    ]);
});

it('counts the people without a schedule and the corrections waiting for RH, never of another company nor whose they are', function (): void {
    App\Modules\Attendance\Models\AttendancePolicy::factory()->create(['tenant_id' => $this->a->id]);
    $center = WorkCenter::factory()->for($this->a)->create();
    $zafiro = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id, 'name' => 'Zafiro Luna']);
    App\Modules\Attendance\Models\CorrectionRequest::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id, 'reason' => 'Cita médica Zafiro']);
    App\Modules\Attendance\Models\CorrectionRequest::factory()->count(3)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    $line = overviewLine($this, 'attendance');

    expect($line)->toMatchArray([
        'state' => 'pending',
        'summary' => '1 persona no tiene horario. Hay 1 solicitud de corrección por atender.',
        'link' => "/admin/{$this->a->slug}/attendance/corrections",
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro');

    Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    App\Modules\Attendance\Models\CorrectionRequest::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id]);

    expect(overviewLine($this, 'attendance')['summary'])->toBe('2 personas no tienen horario. Hay 2 solicitudes de corrección por atender.');
});

it('counts the disputed weeks waiting for RH and sends to them, never whose they are nor why', function (): void {
    App\Modules\Attendance\Models\AttendancePolicy::factory()->create(['tenant_id' => $this->a->id]);
    $zafiro = Employee::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Zafiro Luna']);
    App\Modules\Attendance\Models\AttendanceSchedule::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id]);
    App\Modules\Attendance\Models\WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id]);
    workIn($this->a);

    $line = overviewLine($this, 'attendance');

    expect($line)->toMatchArray([
        'state' => 'pending',
        'summary' => 'Hay 1 semana objetada por resolver.',
        'link' => "/admin/{$this->a->slug}/attendance?status=disputed",
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro')->not->toContain('16:00');
});

it('sends to the setup while someone lacks a schedule, and puts attendance up to date once everyone has one and nothing waits', function (): void {
    App\Modules\Attendance\Models\AttendancePolicy::factory()->create(['tenant_id' => $this->a->id]);
    $person = Employee::factory()->create(['tenant_id' => $this->a->id]);
    workIn($this->a);

    expect(overviewLine($this, 'attendance'))->toMatchArray(['state' => 'pending', 'summary' => '1 persona no tiene horario.', 'link' => "/admin/{$this->a->slug}/attendance/setup"]);

    App\Modules\Attendance\Models\AttendanceSchedule::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $person->id]);

    expect(overviewLine($this, 'attendance'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'Tu política está revisada, todas las personas tienen horario y no hay correcciones ni objeciones por atender ni marcas sin conexión por verificar.',
        'link' => "/admin/{$this->a->slug}/attendance",
    ]);
});

describe('marks made without a connection', function (): void {
    /** A company with its policy and one person with a schedule: only what the test adds is left to attend. */
    function attendanceReady(object $test): Employee
    {
        App\Modules\Attendance\Models\AttendancePolicy::factory()->create(['tenant_id' => $test->a->id]);
        $zafiro = Employee::factory()->create(['tenant_id' => $test->a->id, 'name' => 'Zafiro Luna']);
        App\Modules\Attendance\Models\AttendanceSchedule::factory()->create(['tenant_id' => $test->a->id, 'employee_id' => $zafiro->id]);

        return $zafiro;
    }

    it('counts the ones that need review and sends to them, never whose they are, their note nor their flags', function (): void {
        $zafiro = attendanceReady($this);
        $flag = [App\Modules\Attendance\Enums\ReviewFlag::NoPosition];
        Tests\Support\OfflineDeliveries::accepted($zafiro, '2026-11-02 09:00', flags: $flag, justification: 'Cita médica Zafiro');
        workIn($this->a);

        $line = overviewLine($this, 'attendance');

        expect($line)->toMatchArray([
            'state' => 'pending',
            'summary' => 'Hay 1 marca sin conexión por verificar con banderas o con más de 7 días.',
            'link' => "/admin/{$this->a->slug}/attendance/corrections?status=offline",
        ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro')->not->toContain('Cita médica')->not->toContain('no_position');

        Tests\Support\OfflineDeliveries::accepted($zafiro, '2026-11-03 09:00', receivedAt: CarbonImmutable::now()->subDays(9));

        expect(overviewLine($this, 'attendance')['summary'])->toBe('Hay 2 marcas sin conexión por verificar con banderas o con más de 7 días.');
    });

    it('does not put attendance pending for a recent mark with no flags, a reviewed one, or another company\'s', function (): void {
        $zafiro = attendanceReady($this);
        Tests\Support\OfflineDeliveries::accepted($zafiro, '2026-11-02 09:00', receivedAt: CarbonImmutable::now()->subHour());
        $reviewed = Tests\Support\OfflineDeliveries::accepted($zafiro, '2026-11-03 09:00', flags: [App\Modules\Attendance\Enums\ReviewFlag::WideInterval]);
        Database\Factories\Attendance\MarkReviewFactory::new()->createOne(['tenant_id' => $this->a->id, 'mark_id' => $reviewed->mark_id]);
        workIn($this->b);
        Tests\Support\OfflineDeliveries::accepted(Employee::factory()->create(['tenant_id' => $this->b->id]), '2026-11-02 09:00', flags: [App\Modules\Attendance\Enums\ReviewFlag::NoPosition]);
        workIn($this->a);

        expect(overviewLine($this, 'attendance'))->toMatchArray(['state' => 'up_to_date', 'stateLabel' => 'Al día']);
    });

    it('puts the queue of corrections, then the disputed weeks, before the marks to review', function (): void {
        $zafiro = attendanceReady($this);
        Tests\Support\OfflineDeliveries::accepted($zafiro, '2026-11-02 09:00', flags: [App\Modules\Attendance\Enums\ReviewFlag::NoPosition]);
        App\Modules\Attendance\Models\WeeklyConfirmation::factory()->disputed()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id]);
        workIn($this->a);

        expect(overviewLine($this, 'attendance')['link'])->toBe("/admin/{$this->a->slug}/attendance?status=disputed");

        App\Modules\Attendance\Models\CorrectionRequest::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $zafiro->id]);

        expect(overviewLine($this, 'attendance'))->toMatchArray([
            'link' => "/admin/{$this->a->slug}/attendance/corrections",
            'summary' => 'Hay 1 solicitud de corrección por atender. Hay 1 semana objetada por resolver. Hay 1 marca sin conexión por verificar con banderas o con más de 7 días.',
        ]);
    });

    it('sends to the marks before the setup when both are missing', function (): void {
        App\Modules\Attendance\Models\AttendancePolicy::factory()->create(['tenant_id' => $this->a->id]);
        $person = Employee::factory()->create(['tenant_id' => $this->a->id]);
        Tests\Support\OfflineDeliveries::accepted($person, '2026-11-02 09:00', flags: [App\Modules\Attendance\Enums\ReviewFlag::NoPosition]);
        workIn($this->a);

        expect(overviewLine($this, 'attendance')['link'])->toBe("/admin/{$this->a->slug}/attendance/corrections?status=offline");
    });
});

it('does not tell attendance to a company without it, and offers the overview to a company with only attendance', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'attendance'))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
});

it('tells a company without people that its org chart is empty', function (): void {
    expect(overviewLine($this, 'org_chart'))->toBe([
        'key' => 'org_chart',
        'label' => 'Organigrama',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no cargas a tu gente.',
        'link' => "/admin/{$this->a->slug}/org-chart",
    ]);
});

it('counts the active people and those the chart leaves out, never of another company nor who they are', function (): void {
    $top = Employee::factory()->create(['tenant_id' => $this->a->id]);
    Employee::factory()->reportingTo($top)->create();
    Employee::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Zafiro Luna']);
    Employee::factory()->deactivated()->create(['tenant_id' => $this->a->id]);
    Employee::factory()->count(4)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    $line = overviewLine($this, 'org_chart');

    expect($line)->toMatchArray([
        'state' => 'pending',
        'summary' => 'Tu organigrama tiene 3 personas activas; 1 no tiene jefe ni personas a cargo y no aparece en el árbol.',
        'link' => "/admin/{$this->a->slug}/org-chart/quality",
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro');

    Employee::factory()->count(2)->create(['tenant_id' => $this->a->id]);

    expect(overviewLine($this, 'org_chart')['summary'])->toBe('Tu organigrama tiene 5 personas activas; 3 no tienen jefe ni personas a cargo y no aparecen en el árbol.');
});

it('puts the org chart up to date when everyone is in the tree', function (): void {
    Employee::factory()->reportingTo(Employee::factory()->create(['tenant_id' => $this->a->id]))->create();
    workIn($this->a);

    expect(overviewLine($this, 'org_chart'))->toMatchArray(['state' => 'up_to_date', 'stateLabel' => 'Al día', 'summary' => 'Las 2 personas activas están en el árbol del organigrama.', 'link' => "/admin/{$this->a->slug}/org-chart"]);
});

it('does not tell the org chart to a company without it, and offers the overview to a company with only the org chart', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'org_chart'))->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->a->id, ['org_chart'], CarbonImmutable::now(config('app.display_timezone')));

    expect(array_map(fn ($tool): string => $tool::class, resolve(App\Modules\Assistant\Support\ToolRegistry::class)->for($this->scope)))->toContain(GetComplianceOverview::class);
});

it('tells a company that has Compensación where its collective contract stands, without anything loaded yet', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035', 'compensation'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'compensation'))->toBe([
        'key' => 'compensation',
        'label' => 'Compensación',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no cargas a tu gente para costear el contrato colectivo.',
        'link' => "/admin/{$this->a->slug}/compensation",
    ]);
});

it('does not tell Compensación to a company whose plan does not include it, and tells it once the module is granted', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'compensation'))->toBeNull();

    resolve(App\Modules\Tenancy\Actions\GrantModule::class)->handle($this->a, 'compensation', 'Contratado por separado', null, superadmin());

    expect(overviewLine($this, 'compensation'))->not->toBeNull();
});

it('counts the people the cost covers, the categories without a salary and the scenarios, never a salary nor a person of any company', function (): void {
    Carbon::setTestNow('2026-11-04 10:00:00');
    $grade = App\Modules\Organization\Models\JobGrade::factory()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\GradeSalary::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 98_765, 'effective_from' => '2026-01-01']);
    Employee::factory()->count(2)->create(['tenant_id' => $this->a->id, 'name' => 'Zafiro Luna', 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL']);
    Employee::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => null]);
    App\Modules\Organization\Models\JobGrade::factory()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\Scenario::factory()->count(2)->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    $line = overviewLine($this, 'compensation');

    expect($line)->toMatchArray([
        'state' => 'in_progress',
        'stateLabel' => 'En curso',
        'summary' => 'El costo cubre a 2 de 3 personas; 1 categoría no tiene salario. Aún no armas ningún escenario.',
    ])->and(json_encode($line, JSON_THROW_ON_ERROR))->not->toContain('Zafiro');
    // The summary carries no ids, so a salary cannot hide in it by chance.
    expect($line['summary'])->not->toContain('987');

    Employee::query()->whereNull('job_grade_id')->delete();
    App\Modules\Compensation\Models\GradeSalary::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => App\Modules\Organization\Models\JobGrade::query()->whereKeyNot($grade->id)->value('id'), 'daily_salary_cents' => 60_000, 'effective_from' => '2026-01-01']);
    App\Modules\Compensation\Models\Scenario::factory()->create(['tenant_id' => $this->a->id]);

    expect(overviewLine($this, 'compensation'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'El costo cubre a las 2 personas. Tienes 1 escenario activo.',
    ]);

    App\Modules\Compensation\Models\Scenario::factory()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\GradeSalary::query()->delete();
    App\Modules\Organization\Models\JobGrade::factory()->create(['tenant_id' => $this->a->id]);

    expect(overviewLine($this, 'compensation')['summary'])->toBe('El costo cubre a las 2 personas; 3 categorías no tienen salario. Tienes 2 escenarios activos.');
});

it('tells a company that only costs an average payroll that it has started, and where to go to cost it person by person', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035', 'compensation'], CarbonImmutable::now(config('app.display_timezone')));
    App\Modules\Compensation\Models\Scenario::factory()->average()->count(2)->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\Scenario::factory()->average()->archived()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\Scenario::factory()->average()->create(['tenant_id' => $this->b->id]);
    workIn($this->a);

    $line = overviewLine($this, 'compensation');

    // Nothing of the average (the headcount, the salary) nor of the other company: only how many.
    expect($line)->toBe([
        'key' => 'compensation',
        'label' => 'Compensación',
        'state' => 'in_progress',
        'stateLabel' => 'En curso',
        'summary' => 'Aún no cargas a tu gente para costear de sueldo en sueldo. Tienes 2 escenarios con una plantilla promedio.',
        'link' => "/admin/{$this->a->slug}/compensation/quick",
    ]);
});

it('adds the scenarios of an average payroll to what it tells of a company that has loaded its people', function (): void {
    $grade = App\Modules\Organization\Models\JobGrade::factory()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\GradeSalary::factory()->create(['tenant_id' => $this->a->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    Employee::factory()->count(2)->create(['tenant_id' => $this->a->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL']);
    App\Modules\Compensation\Models\Scenario::factory()->create(['tenant_id' => $this->a->id]);
    App\Modules\Compensation\Models\Scenario::factory()->average()->create(['tenant_id' => $this->a->id]);
    Carbon::setTestNow('2026-11-04 10:00:00');
    workIn($this->a);

    expect(overviewLine($this, 'compensation')['summary'])->toBe('El costo cubre a las 2 personas. Tienes 1 escenario activo y 1 con una plantilla promedio.');
});

it('tells someone who may not see compensation nothing of it', function (): void {
    App\Modules\Compensation\Models\Scenario::factory()->create(['tenant_id' => $this->a->id]);
    $this->scope = assistantScopeIn($this->a, TenantRole::Viewer);

    expect(overviewLine($this, 'compensation'))->toBe([
        'key' => 'compensation',
        'label' => 'Compensación',
        'state' => 'not_applicable',
        'stateLabel' => 'No aplica',
        'summary' => 'Compensación solo la ven quienes tienen ese permiso.',
        'link' => "/admin/{$this->a->slug}",
    ]);
});

/** A published policy of prevention of `$tenant` that reached `$center`. */
function humiPolicyReaching(Tenant $tenant, WorkCenter $center): App\Modules\Disclosure\Models\Document
{
    $policy = App\Modules\Disclosure\Models\Document::factory()->published()->create(['tenant_id' => $tenant->id]);
    App\Modules\Disclosure\Models\DocumentCenter::factory()->create(['tenant_id' => $tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);

    return $policy;
}

it('tells a company that has not published its policy of prevention', function (): void {
    expect(overviewLine($this, 'disclosure'))->toBe([
        'key' => 'disclosure',
        'label' => 'Difusión',
        'state' => 'pending',
        'stateLabel' => 'Pendiente',
        'summary' => 'Aún no publicas tu política de prevención de riesgos psicosociales.',
        'link' => "/admin/{$this->a->slug}/disclosure/policy",
    ]);
});

it('counts the centers in use the policy reached, never those of another company', function (): void {
    $plant = WorkCenter::factory()->for($this->a)->create();
    $offices = WorkCenter::factory()->for($this->a)->create();
    WorkCenter::factory()->for($this->a)->create(['archived_at' => now()]);
    $policy = humiPolicyReaching($this->a, $plant);
    humiPolicyReaching($this->b, WorkCenter::factory()->for($this->b)->create());
    workIn($this->a);

    expect(overviewLine($this, 'disclosure'))->toMatchArray(['state' => 'pending', 'summary' => 'Tu política de prevención está publicada en 1 de 2 centros de trabajo.']);

    App\Modules\Disclosure\Models\DocumentCenter::factory()->create(['tenant_id' => $this->a->id, 'document_id' => $policy->id, 'work_center_id' => $offices->id]);

    expect(overviewLine($this, 'disclosure'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'Tu política de prevención está publicada en tus 2 centros de trabajo.',
    ]);
});

it('does not tell the disclosure to a company without NOM-035', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom037'], CarbonImmutable::now(config('app.display_timezone')));

    expect(overviewLine($this, 'disclosure'))->toBeNull();
});
