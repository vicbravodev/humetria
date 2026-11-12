<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ExportOrgChart;
use App\Modules\Organization\Http\Controllers\OrgChartQualityController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

mutates(ExportOrgChart::class, OrgChartQualityController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta, Norte']);
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'area_id' => $area->id];
    $this->dir = Employee::factory()->create([...$this->place, 'name' => 'Dir', 'job_title' => 'Directora']);
    $this->ana = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Ana "La Jefa"', 'job_title' => 'Operadora']);
});

it('downloads the list as a CSV for Excel: the people by name with their manager and the size of their team', function (): void {
    $response = $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality.export', $this->tenant))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename=organigrama.csv')
        ->and($response->streamedContent())->toBe(
            "\u{FEFF}Nombre,Puesto,Área,Centro de trabajo,Jefe directo,Directos\n".
            "\"Ana \"\"La Jefa\"\"\",Operadora,Producción,\"Planta, Norte\",Dir,0\n".
            "Dir,Directora,Producción,\"Planta, Norte\",,1\n",
        );
});

it('does not let a cell run as a formula in the spreadsheet', function (): void {
    $this->ana->update(['name' => '=HYPERLINK("http://example.com")', 'job_title' => '+cmd']);

    $csv = $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality.export', $this->tenant))->streamedContent();

    expect($csv)->toContain("\"'=HYPERLINK(\"\"http://example.com\"\")\",'+cmd,");
});

it('leaves out whoever left and the people of another company, and never writes contact data', function (): void {
    Employee::factory()->deactivated()->create([...$this->place, 'name' => 'Baja']);
    Employee::factory()->create(['name' => 'Ajena']);

    $csv = $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality.export', $this->tenant))->streamedContent();

    expect($csv)->not->toContain('Baja', 'Ajena', (string) $this->ana->email, (string) $this->ana->mobile, 'correo', 'celular');
});

it('is for whoever may see the org chart, and says in the log that it was exported', function (TenantRole $role, int $status): void {
    Log::spy();

    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.org-chart.quality.export', $this->tenant))->assertStatus($status);

    if ($status === 200) {
        Log::shouldHaveReceived('info')->with('org_chart.exported', Mockery::on(fn (array $context): bool => $context['people'] === 2));
    } else {
        Log::shouldNotHaveReceived('info', ['org_chart.exported', Mockery::any()]);
    }
})->with([
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 200],
    'committee' => [TenantRole::Committee, 403],
]);

it('is not for outsiders, nor for another company, nor for a union account', function (): void {
    $this->get(route('tenant.org-chart.quality.export', $this->tenant))->assertRedirect(route('login'));

    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))->get(route('tenant.org-chart.quality.export', $this->tenant))->assertNotFound();

    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.org-chart.quality.export', $union))->assertNotFound();
});
