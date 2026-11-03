<?php

declare(strict_types=1);

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Assistant\Support\Readers\Nom035Reader;
use App\Modules\Assistant\Support\Readers\Nom037Reader;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

mutates(GetComplianceOverview::class, Nom035Reader::class, Nom037Reader::class, ComplianceReaders::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
});

/** The row of one norm in what the tool answers. */
function normRow(array $card, string $key): array
{
    return array_values(array_filter($card['rows'], fn (array $row): bool => $row['key'] === $key))[0];
}

function centerOf(Tenant $tenant, string $name = 'Planta Apodaca'): WorkCenter
{
    return WorkCenter::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
}

it('says a company without work centers has nothing evaluated yet and no telework to review', function (): void {
    $card = toolCard(GetComplianceOverview::class, $this->scope);

    expect($card['render'])->toBe('status_list')
        ->and($card['link'])->toBe("/admin/{$this->a->slug}")
        ->and(array_column($card['rows'], 'key'))->toBe(['nom035', 'nom037'])
        ->and(normRow($card, 'nom035'))->toMatchArray(['label' => 'NOM-035', 'state' => 'pending', 'stateLabel' => 'Pendiente', 'summary' => 'Aún no registras centros de trabajo.'])
        ->and(normRow($card, 'nom037'))->toMatchArray(['label' => 'NOM-037', 'state' => 'not_applicable', 'stateLabel' => 'No aplica']);
});

it('puts NOM-035 pending while some center was never evaluated or is overdue', function (): void {
    centerOf($this->a, 'Nunca evaluado');
    $overdue = centerOf($this->a, 'Vencido');
    Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $overdue->id, 'launched_at' => now()->subYears(3)->subDays(20), 'closed_at' => now()->subYears(3)]);
    $upToDate = centerOf($this->a, 'Al día');
    Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $upToDate->id]);

    $row = normRow(toolCard(GetComplianceOverview::class, $this->scope), 'nom035');

    expect($row['state'])->toBe('pending')
        ->and($row['summary'])->toBe('De 3 centros de trabajo: 1 al día, 0 en curso, 2 pendientes.')
        ->and($row['link'])->toBe("/admin/{$this->a->slug}/campaigns");
});

it('puts NOM-035 in progress when nothing is pending and an evaluation is running, and up to date when all are', function (): void {
    $running = centerOf($this->a, 'En curso');
    Campaign::factory()->active()->create(['tenant_id' => $this->a->id, 'work_center_id' => $running->id]);
    $done = centerOf($this->a, 'Al día');
    Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $done->id]);

    expect(normRow(toolCard(GetComplianceOverview::class, $this->scope), 'nom035')['state'])->toBe('in_progress');

    Campaign::query()->where('work_center_id', $running->id)->update(['status' => 'closed', 'closed_at' => now()]);

    expect(normRow(toolCard(GetComplianceOverview::class, $this->scope), 'nom035')['state'])->toBe('up_to_date');
});

it('ignores the work centers, the campaigns and the telework of another company', function (): void {
    $foreign = centerOf($this->b);
    Campaign::factory()->closed()->create(['tenant_id' => $this->b->id, 'work_center_id' => $foreign->id]);
    $employee = Employee::factory()->create(['tenant_id' => $this->b->id, 'work_center_id' => $foreign->id]);
    Teleworker::factory()->active()->create(['tenant_id' => $this->b->id, 'employee_id' => $employee->id]);

    $card = toolCard(GetComplianceOverview::class, $this->scope);

    expect(normRow($card, 'nom035')['summary'])->toBe('Aún no registras centros de trabajo.')
        ->and(normRow($card, 'nom037')['state'])->toBe('not_applicable');
});

it('tells where NOM-037 stands from the rows of the inspection table of the centers where someone teleworks', function (): void {
    $center = centerOf($this->a);
    $employee = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    Teleworker::factory()->active()->create(['tenant_id' => $this->a->id, 'employee_id' => $employee->id]);

    $row = normRow(toolCard(GetComplianceOverview::class, $this->scope), 'nom037');

    expect($row['state'])->toBe('in_progress')
        ->and($row['summary'])->toMatch('/^Cumples \d+ de \d+ puntos de la tabla 10\.3 en 1 centro con teletrabajo\.$/')
        ->and($row['link'])->toBe("/admin/{$this->a->slug}/telework");
});

it('lists a new norm as soon as its reader is registered, without touching the tool', function (): void {
    $reader = new class implements ComplianceReader
    {
        public function status(): ComplianceStatus
        {
            return new ComplianceStatus('ley_silla', 'Ley Silla', 'pending', 'Pendiente', 'Aún no empiezas.', '/admin/acme/seating');
        }
    };
    app()->instance(ComplianceReaders::class, new ComplianceReaders([$reader]));

    $card = toolCard(GetComplianceOverview::class, $this->scope);

    expect(array_column($card['rows'], 'key'))->toBe(['ley_silla']);
});

it('asks the model for nothing', function (): void {
    expect(resolve(GetComplianceOverview::class, ['scope' => $this->scope])->schema(new JsonSchemaTypeFactory))->toBe([]);
});
