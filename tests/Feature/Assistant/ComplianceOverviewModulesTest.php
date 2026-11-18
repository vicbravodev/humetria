<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\Readers\SeatingReader;
use App\Modules\Assistant\Support\Readers\VacationsReader;
use App\Modules\Assistant\Tools\GetComplianceOverview;
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

mutates(SeatingReader::class, VacationsReader::class);

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

function routeOf(int $done): RouteProgress
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
    seatingRouteAnswers($this->a, routeOf(6), routeOf(6), RouteProgress::notApplicable('Nadie trabaja de pie'));

    expect(overviewLine($this, 'seating'))->toMatchArray([
        'state' => 'up_to_date',
        'stateLabel' => 'Al día',
        'summary' => 'Cumples 12 de 12 etapas de la ruta en 2 centros con puestos de pie.',
    ]);
});

it('puts Ley Silla pending while a center has not started its route', function (): void {
    seatingRouteAnswers($this->a, routeOf(6), routeOf(0));

    expect(overviewLine($this, 'seating'))->toMatchArray(['state' => 'pending', 'stateLabel' => 'Pendiente']);
});

it('puts Ley Silla pending while a center has not said whether it applies there', function (): void {
    seatingRouteAnswers($this->a, routeOf(6), RouteProgress::unanswered(), RouteProgress::unanswered());

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

    expect(toolCard(GetComplianceOverview::class, $this->scope)['rows'])->toHaveCount(1)
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
