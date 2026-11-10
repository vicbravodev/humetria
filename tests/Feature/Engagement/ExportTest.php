<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\ResultsCsv;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(ResultsCsv::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

/**
 * @return list<list<string|null>> the rows of the CSV the company downloaded, header first
 */
function climateExportRows(Tenant $tenant, Survey $survey, string $query = ''): array
{
    $response = test()->get(route('tenant.climate.export', [$tenant, $survey]).$query)->assertOk();
    $text = ltrim($response->streamedContent(), "\xEF\xBB\xBF");

    return array_map(str_getcsv(...), array_values(array_filter(explode("\n", $text))));
}

it('downloads the company results with the columns of the screen', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6, fn (int $i): array => ['clarity.goals' => $i < 3 ? 4 : 2]);

    $response = $this->actingAs($this->hr)->get(route('tenant.climate.export', [$this->tenant, $survey]));
    $rows = climateExportRows($this->tenant, $survey);

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($rows[0])->toBe(['grupo', 'driver', 'pregunta', 'favorable', 'neutral', 'desfavorable', 'respuestas'])
        ->and($rows)->toContain(['Toda la empresa', 'Claridad y alineación', 'Conozco las principales metas que busca alcanzar esta empresa.', '50', '0', '50', '6']);
});

it('leaves the cells of a question empty when too few answered it', function (): void {
    $survey = climateResultsSurvey($this->tenant, 8, fn (int $i): array => ['clarity.goals' => $i < 4 ? 5 : null, 'clarity.priorities' => 4]);

    $this->actingAs($this->hr);
    $rows = climateExportRows($this->tenant, $survey);

    expect($rows)->toContain(['Toda la empresa', 'Claridad y alineación', 'Conozco las principales metas que busca alcanzar esta empresa.', '', '', '', '']);
});

it('exports one cut with only the groups the screen shows and says nothing of the ones it hides', function (): void {
    $big = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $survey = climateResultsSurvey($this->tenant, 8, segment: ['area_id' => $big->id]);
    foreach (['Compras', 'Dirección'] as $name) {
        $small = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name]);
        foreach (range(1, 3) as $ignored) {
            Response::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'area_id' => $small->id, 'answers' => ['clarity.goals' => 1]]);
        }
    }

    $this->actingAs($this->hr);
    $rows = climateExportRows($this->tenant, $survey, '?cut=area');
    $groups = array_values(array_unique(array_column(array_slice($rows, 1), 0)));
    $text = json_encode($rows, JSON_THROW_ON_ERROR);

    expect($groups)->toBe(['Ventas'])
        ->and($text)->not->toContain('Compras')->not->toContain('Dirección')->not->toContain('Toda la empresa');
});

it('does not export comments nor any individual response', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'body' => 'Faltan sillas en la bodega']);

    $this->actingAs($this->hr);
    $text = json_encode(climateExportRows($this->tenant, $survey), JSON_THROW_ON_ERROR);

    expect($text)->not->toContain('Faltan sillas');
    foreach (Response::query()->pluck('id') as $id) {
        expect($text)->not->toContain($id);
    }
});

it('refuses a cut that is not one, an open survey, another company and a role that does not view climate', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);
    $open = climateResultsSurvey($this->tenant, 6, closed: false);
    $other = Tenant::factory()->create();
    $foreign = climateResultsSurvey($other, 6);
    workIn($this->tenant);

    $this->actingAs($this->hr);
    $this->getJson(route('tenant.climate.export', [$this->tenant, $survey]).'?cut[]=area&cut[]=tenure')->assertUnprocessable();
    $this->get(route('tenant.climate.export', [$this->tenant, $open]))->assertNotFound();
    $this->get(route('tenant.climate.export', [$this->tenant, $foreign]))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.climate.export', [$this->tenant, $survey]))->assertForbidden();
});

it('writes a log and an audit entry that say the survey and the cut and carry no figure', function (): void {
    Log::spy();
    $survey = climateResultsSurvey($this->tenant, 6);

    $this->actingAs($this->hr)->get(route('tenant.climate.export', [$this->tenant, $survey]))->assertOk()->streamedContent();

    $entry = AuditEntry::query()->where('event', 'engagement.results_exported')->sole();
    expect($entry->subject_id)->toBe($survey->id)->and($entry->metadata)->toBe(['cut' => null]);
    Log::shouldHaveReceived('info')->with('engagement.results_exported', ['survey_id' => $survey->id, 'cut' => null])->once();
});

it('writes a cell that starts like a formula as text', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '=HYPERLINK("http://x")']);
    $survey = climateResultsSurvey($this->tenant, 6, segment: ['area_id' => $area->id]);

    $this->actingAs($this->hr);
    $rows = climateExportRows($this->tenant, $survey, '?cut=area');

    expect($rows[1][0])->toBe('\'=HYPERLINK("http://x")');
});
