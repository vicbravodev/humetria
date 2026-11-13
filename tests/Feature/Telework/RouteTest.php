<?php

declare(strict_types=1);

use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Support\CompanyTeleworkRoute;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TeleworkProfileFactory;
use Database\Factories\Telework\WorkerNoticeFactory;

mutates(CompanyTeleworkRoute::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
});

/** Someone who teleworks from a center of the test's company. */
function teleworkerAt(WorkCenter $center, string $name = 'Hugo Saiz'): string
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => $center->id, 'name' => $name]);

    return TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employee->id])->id;
}

// Rediseño v2 (decisión A2): where nobody teleworks yet, the center's answer to «¿Te aplica?» decides, not the empty padrón.
it('tells each center where it stands in its route, and that it still has to answer where nobody teleworks', function (): void {
    // In the padrón with the equipment answered, but with no workplace yet, so no rules, places or reviews.
    teleworkerAt($this->plant);

    expect(app(TeleworkRoute::class)->progress([$this->plant->id, $this->offices->id, WorkCenter::factory()->create()->id]))->toEqual([
        $this->plant->id => new RouteProgress(true, [false, false, false, true, true, false], 'define las reglas'),
        $this->offices->id => RouteProgress::unanswered(),
    ]);
});

it('does not apply where the center declared that nobody works away more than 40 % of the time, with the day of its certificate', function (): void {
    TeleworkProfileFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id, 'declared_on' => '2026-02-03']);

    expect(app(TeleworkRoute::class)->progress([$this->offices->id]))->toEqual([
        $this->offices->id => RouteProgress::notApplicable('Nadie teletrabaja más del 40 % · constancia del 3 feb 2026'),
    ]);
});

it('starts the route where the center declared that someone teleworks, with the people still to register', function (): void {
    TeleworkProfileFactory::new()->teleworks()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);

    expect(app(TeleworkRoute::class)->progress([$this->offices->id]))->toEqual([
        // C-02 (behavior changed on purpose): with nobody in the padrón and no notice, «cada aviso revisado» is not done either.
        $this->offices->id => new RouteProgress(true, [false, false, false, false, false, false], 'define las reglas'),
    ]);
});

it('follows the padrón over a declaration that nobody teleworks', function (): void {
    TeleworkProfileFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    teleworkerAt($this->plant);

    expect(app(TeleworkRoute::class)->progress([$this->plant->id])[$this->plant->id]->applies)->toBeTrue();
});

it('gathers by center the accident notices nobody reviewed yet, naming the person and the notice when there is one', function (): void {
    $hugo = teleworkerAt($this->plant);
    $lone = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'submitted_at' => '2026-02-27 18:00:00']);
    // Reviewed, or another kind of notice: neither waits.
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'reviewed_at' => now(), 'reviewed_by' => ownerOf($this->tenant)->id, 'review_note' => 'Se ajustó la silla.']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'kind' => NoticeKind::ConditionChange]);

    foreach (['Ana Ruiz', 'Luis Peña'] as $index => $name) {
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => teleworkerAt($this->offices, $name), 'submitted_at' => "2026-02-2{$index} 18:00:00"]);
    }

    expect(app(TeleworkRoute::class)->accidents())->toEqual([
        new UnreviewedAccidents($this->offices->id, 2, null, '2026-02-20'),
        new UnreviewedAccidents($this->plant->id, 1, 'Hugo Saiz', '2026-02-27', $lone->id),
    ]);
});

it('has no accident to tell without notices', function (): void {
    teleworkerAt($this->plant);

    expect(app(TeleworkRoute::class)->accidents())->toBe([]);
});
