<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Support\WorkerSections;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WorkerSections::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    // A company whose tools offer nothing of their own here (no NOM-037, no registro de jornada): only the tools of each test.
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
});

/** A section that answers `$answer` for the person it is asked about, tagged as every module tags theirs. */
function workerSection(string $name, Closure $answer): void
{
    app()->bind($name, fn (): WorkerSection => new readonly class($answer) implements WorkerSection
    {
        public function __construct(private Closure $answer) {}

        public function for(WorkerIdentity $person): ?WorkerSectionLink
        {
            return ($this->answer)($person);
        }
    });
    app()->tag([$name], 'worker.sections');
}

/** @return list<array<string, mixed>> The sections of the start page of the session open now. */
function homeSections(): array
{
    $sections = [];
    test()->get(route('worker.home', 'acme'))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $page->component('worker-access/home');
        $sections = $page->toArray()['props']['sections'];
    });

    return $sections;
}

it('lists what each tool offers the person, in the fixed order of the start, with what waits in each', function (): void {
    workerSection('test.vacations', fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink('vacations', 'Mis vacaciones', 'Su saldo.', '/me/acme/vacations', 2));
    workerSection('test.telework', fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink('telework', 'Su teletrabajo', 'Sus pendientes.', '/me/acme/telework'));
    workerSection('test.unknown', fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink('something-new', 'Algo nuevo', 'Al final.', '/me/acme/new'));
    workerSection('test.attendance', fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink('attendance', 'Marcar', 'Su entrada.', '/a/acme'));
    enteredAs($this->ana->id);

    expect(homeSections())->toBe([
        ['key' => 'telework', 'label' => 'Su teletrabajo', 'description' => 'Sus pendientes.', 'href' => '/me/acme/telework', 'pending' => null],
        ['key' => 'attendance', 'label' => 'Marcar', 'description' => 'Su entrada.', 'href' => '/a/acme', 'pending' => null],
        ['key' => 'vacations', 'label' => 'Mis vacaciones', 'description' => 'Su saldo.', 'href' => '/me/acme/vacations', 'pending' => 2],
        ['key' => 'something-new', 'label' => 'Algo nuevo', 'description' => 'Al final.', 'href' => '/me/acme/new', 'pending' => null],
    ]);
});

it('asks each tool about the person of the session only, and leaves out the tools with nothing for them', function (): void {
    workerSection('test.mine', fn (WorkerIdentity $person): ?WorkerSectionLink => $person->employeeId === $this->ana->id
        ? new WorkerSectionLink('team', "Equipo de {$person->personName}", 'Su equipo.', '/me/acme/team') : null);
    workerSection('test.theirs', fn (WorkerIdentity $person): ?WorkerSectionLink => $person->employeeId === $this->beto->id
        ? new WorkerSectionLink('seating', 'Ley Silla de Beto Ruiz', 'De otra persona.', '/me/acme/seating') : null);
    enteredAs($this->ana->id);

    expect(array_column(homeSections(), 'label'))->toBe(['Equipo de Ana López']);
    $this->get(route('worker.home', 'acme'))->assertDontSee('Beto Ruiz');
});

it('keeps the start open when a tool fails: the others are still listed and the failure is reported', function (): void {
    workerSection('test.broken', fn (WorkerIdentity $person): never => throw new RuntimeException('broken tool'));
    workerSection('test.team', fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink('team', 'Mi equipo', 'Su equipo.', '/me/acme/team'));
    $reported = [];
    app(Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (RuntimeException $exception) use (&$reported): void {
        $reported[] = $exception->getMessage();
    });
    enteredAs($this->ana->id);

    expect(array_column(homeSections(), 'key'))->toBe(['team'])->and($reported)->toBe(['broken tool']);
});

it('lists nothing while no tool offers anything, and still lets the person leave and add a key', function (): void {
    enteredAs($this->ana->id, EntryVia::Key, 'key-1');

    $this->get(route('worker.home', 'acme'))->assertInertia(fn (Assert $page) => $page->component('worker-access/home')
        ->where('sections', [])
        ->where('leaveUrl', route('worker.leave', 'acme'))
        ->where('keyUrl', route('worker.key.show', 'acme'))
        ->etc());
});

it('reads the start with the same queries whatever the tools offer', function (int $tools): void {
    foreach (range(1, $tools) as $index) {
        workerSection("test.tool{$index}", fn (WorkerIdentity $person): WorkerSectionLink => new WorkerSectionLink("tool{$index}", 'Herramienta', 'Algo.', '/me/acme/tool'));
    }
    enteredAs($this->ana->id);

    $this->expectsDatabaseQueryCount(WORKER_HOME_QUERIES);

    $this->get(route('worker.home', 'acme'))->assertOk();
})->with([1, 5]);

/**
 * The company of the slug, the session (the person's state, the person and their area) and the person's keys; then the
 * company again (its row and its tools) for each tool that asks whether it has the module: Telework, Marcar, Mi equipo, Mis
 * vacaciones (which also asks whether the company opened Vacaciones), Mi puesto de pie, Política por confirmar, Avisos y
 * resultados de su centro, Nos dijeron / Haremos, Retroalimentación de mi equipo and Apoyo; and the open invitations of the
 * person to a climate or pulse survey, which read the company only when there is one.
 */
const WORKER_HOME_QUERIES = 30;
