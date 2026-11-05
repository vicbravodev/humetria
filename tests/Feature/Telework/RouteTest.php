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

it('tells each center where it stands in its route, and that it does not apply where nobody teleworks', function (): void {
    // In the padrón with the equipment answered, but with no workplace yet, so no rules, places or reviews.
    teleworkerAt($this->plant);

    expect(app(TeleworkRoute::class)->progress([$this->plant->id, $this->offices->id, WorkCenter::factory()->create()->id]))->toEqual([
        $this->plant->id => new RouteProgress(true, [false, false, false, true, true, false], 'define las reglas'),
        $this->offices->id => RouteProgress::notApplicable('Nadie teletrabaja en este centro'),
    ]);
});

it('gathers by center the accident notices nobody reviewed yet, naming the person when there is one', function (): void {
    $hugo = teleworkerAt($this->plant);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'submitted_at' => '2026-02-27 18:00:00']);
    // Reviewed, or another kind of notice: neither waits.
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'reviewed_at' => now(), 'reviewed_by' => ownerOf($this->tenant)->id, 'review_note' => 'Se ajustó la silla.']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $hugo, 'kind' => NoticeKind::ConditionChange]);

    foreach (['Ana Ruiz', 'Luis Peña'] as $index => $name) {
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => teleworkerAt($this->offices, $name), 'submitted_at' => "2026-02-2{$index} 18:00:00"]);
    }

    expect(app(TeleworkRoute::class)->accidents())->toEqual([
        new UnreviewedAccidents($this->offices->id, 2, null, '2026-02-20'),
        new UnreviewedAccidents($this->plant->id, 1, 'Hugo Saiz', '2026-02-27'),
    ]);
});

it('has no accident to tell without notices', function (): void {
    teleworkerAt($this->plant);

    expect(app(TeleworkRoute::class)->accidents())->toBe([]);
});
