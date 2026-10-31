<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Actions\SaveReportDetails;
use App\Modules\Results\Http\Controllers\ReportController;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportController::class, ReportBuilder::class, SaveReportDetails::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'legal_name' => 'Autopartes Acme, S.A. de C.V.', 'rfc' => 'AAC010101AB1']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(workCenterForm(['interior_number' => null]));
    $this->evaluation = Campaign::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'work_center_id' => $this->plant->id,
        'instruments' => ['nom035.guide-i', 'nom035.guide-iii'],
        'workers' => 80,
        'population' => Population::Sample,
        'sample_size' => 67,
        'quotas' => ['female' => 67],
        'launched_at' => '2026-11-02 16:00:00',
        'closed_at' => '2026-11-19 18:00:00',
    ]);
});

/** What HR fills in for the report. */
function reportForm(array $overrides = []): array
{
    return [
        'objective' => 'Identificar los factores de riesgo psicosocial de la planta.',
        'activities' => 'Ensamble y pintura de autopartes.',
        'conclusions' => 'La mayoría de los cuestionarios quedan en riesgo medio.',
        'recommendations' => 'Programa de intervención en carga de trabajo.',
        'responsible_name' => 'Dra. Laura Méndez',
        'responsible_license' => '12345678',
        ...$overrides,
    ];
}

it('builds the 7.7 report of a closed evaluation, with what the platform knows and defaults for the rest', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $this->evaluation->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-iii', 'answers' => nom035Answers('nom035.guide-iii', 'never')]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('results/report')
            ->where('report.center', [
                'legalName' => 'Autopartes Acme, S.A. de C.V.',
                'rfc' => 'AAC010101AB1',
                'name' => 'Planta Querétaro',
                'address' => 'Av. 5 de Febrero 1200, col. Zona Industrial Benito Juárez, C.P. 76120, Querétaro, Querétaro',
                'mainActivity' => 'Manufactura de autopartes',
            ])
            ->where('report.guide', 'Guía III')
            ->where('report.itemCount', 72)
            ->where('report.period', '2 nov 2026 al 19 nov 2026')
            ->where('report.participation', ['invited' => 0, 'answered' => 0])
            ->where('report.method', 'Cuestionario de la Guía de referencia III de la NOM-035-STPS-2018 (DOF 23/10/2018), aplicado en línea del 2 nov 2026 al 19 nov 2026 a una muestra representativa de 67 de 80 trabajadores (III.1), seleccionada al azar y en proporción por género, y la Guía de referencia I a todas las personas. Cada cuestionario se calificó por sí solo, por categoría, dominio y calificación final (III.3), sin promedios. Los resultados de grupos con menos de 5 cuestionarios no se muestran, para proteger el anonimato de las respuestas.')
            ->where('report.objective', 'Identificar y analizar los factores de riesgo psicosocial y evaluar el entorno organizacional del centro de trabajo, conforme a la NOM-035-STPS-2018.')
            ->where('report.activities', 'Manufactura de autopartes')
            ->where('report.conclusions', null)
            ->where('report.recommendations', null)
            ->where('report.responsible', null)
            ->where('report.complete', false)
            ->where('report.results.answered', 5)
            ->where('report.results.final', [0, 0, 0, 0, 5])
            ->where('report.areas.visible.0.name', 'Producción')
            ->has('levels', 5)
            ->where('canEdit', true)
            // Starts from what the report already says: HR edits it rather than writing it from nothing.
            ->where('form', [
                'objective' => 'Identificar y analizar los factores de riesgo psicosocial y evaluar el entorno organizacional del centro de trabajo, conforme a la NOM-035-STPS-2018.',
                'activities' => 'Manufactura de autopartes',
                'conclusions' => '',
                'recommendations' => '',
                'responsible_name' => '',
                'responsible_license' => '',
            ]));

    expect(AuditEntry::query()->where('event', 'results.report_viewed')->sole()->metadata)->toBe(['campaign_id' => $this->evaluation->id]);
});

it('says the evaluation was for everyone, with Guía II', function (): void {
    $everyone = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'workers' => 30, 'launched_at' => '2026-11-02 16:00:00', 'closed_at' => '2026-11-19 18:00:00']);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.report', [$this->tenant, $everyone->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.guide', 'Guía II')
            ->where('report.objective', 'Identificar y analizar los factores de riesgo psicosocial del centro de trabajo, conforme a la NOM-035-STPS-2018.')
            ->where('report.method', 'Cuestionario de la Guía de referencia II de la NOM-035-STPS-2018 (DOF 23/10/2018), aplicado en línea del 2 nov 2026 al 19 nov 2026 a todos los trabajadores del centro (30), y la Guía de referencia I a todas las personas. Cada cuestionario se calificó por sí solo, por categoría, dominio y calificación final (II.3), sin promedios. Los resultados de grupos con menos de 5 cuestionarios no se muestran, para proteger el anonimato de las respuestas.'));
});

it('saves what HR fills in, and shows it in the report', function (): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm())
        ->assertRedirect(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.objective', 'Identificar los factores de riesgo psicosocial de la planta.')
            ->where('report.activities', 'Ensamble y pintura de autopartes.')
            ->where('report.conclusions', 'La mayoría de los cuestionarios quedan en riesgo medio.')
            ->where('report.recommendations', 'Programa de intervención en carga de trabajo.')
            ->where('report.responsible', ['name' => 'Dra. Laura Méndez', 'license' => '12345678'])
            ->where('report.complete', true)
            ->where('form', reportForm()));
});

it('updates what was saved, without a second row, and logs it without its text', function (): void {
    Log::spy();
    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm());
    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm(['responsible_license' => '', 'conclusions' => 'Otra conclusión.']));

    expect(ReportDetails::query()->withoutGlobalScopes()->sole())
        ->conclusions->toBe('Otra conclusión.')
        ->responsible_license->toBeNull()
        ->updated_by->toBe($this->hr->id);
    Log::shouldHaveReceived('info')->with('result_report.saved', ['campaign_id' => $this->evaluation->id])->twice();
});

it('requires every part of the report but the professional license', function (string $field, string $value, string $message): void {
    $this->actingAs($this->hr)
        ->from(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm([$field => $value]))
        ->assertSessionHasErrors([$field => $message]);

    expect(ReportDetails::query()->withoutGlobalScopes()->exists())->toBeFalse();
})->with([
    ['objective', '', 'El campo objetivo es obligatorio.'],
    ['activities', '', 'El campo actividades principales es obligatorio.'],
    ['conclusions', '', 'El campo conclusiones es obligatorio.'],
    ['recommendations', '', 'El campo recomendaciones y acciones de intervención es obligatorio.'],
    ['responsible_name', '', 'El campo nombre del responsable es obligatorio.'],
    ['conclusions', str_repeat('a', 5001), 'El campo conclusiones no debe tener más de 5000 caracteres.'],
    ['responsible_name', str_repeat('a', 151), 'El campo nombre del responsable no debe tener más de 150 caracteres.'],
    ['responsible_license', str_repeat('1', 21), 'El campo cédula profesional no debe tener más de 20 caracteres.'],
]);

it('lets only those who manage campaigns fill in the report', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)
        ->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm())
        ->assertForbidden();
    $this->actingAs($viewer)
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false)->where('form', null));
});

it('has no report for an evaluation still open, a Guía I campaign or another company\'s evaluation', function (Closure $campaign): void {
    $id = $campaign($this->tenant)->id;

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $id]))->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $id]), reportForm())->assertNotFound();
})->with([
    'open' => fn (Tenant $tenant) => Campaign::factory()->active()->create(['tenant_id' => $tenant->id]),
    'Guía I' => fn (Tenant $tenant) => Campaign::factory()->trauma()->create(['tenant_id' => $tenant->id, 'status' => 'closed', 'closed_at' => now()]),
    'another company' => fn (Tenant $tenant) => Campaign::factory()->closed()->create(),
]);

it('gives no single answer in the report', function (): void {
    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $this->evaluation->id, 'instrument' => 'nom035.guide-iii', 'answers' => nom035Answers('nom035.guide-iii')]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(fn (Assert $page) => expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain('"answers"')->not->toContain('"items"'));
});

it('builds the report with a fixed number of queries, however many areas, answers and links', function (int $areas): void {
    foreach (range(1, $areas) as $ignored) {
        $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
        AnonymousResponse::factory()->count(5)->create(['campaign_id' => $this->evaluation->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-iii', 'answers' => nom035Answers('nom035.guide-iii', 'never')]);
        ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'created_at' => now(), 'expires_at' => now()->addHour()]);
    }
    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $this->evaluation->id]), reportForm());
    $url = route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]);
    $this->get($url);

    // The same whatever the size: nothing is read per row.
    $this->expectsDatabaseQueryCount(18);

    $this->get($url)->assertOk();
})->with([1, 4]);
