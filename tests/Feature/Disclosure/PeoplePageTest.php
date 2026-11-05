<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\SaveWriting;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Support\ResultThemes;
use App\Modules\Disclosure\Support\WorkersPages;
use App\Modules\Disclosure\Support\Writing\Templates;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WorkersPages::class, ResultThemes::class);

// Rediseño v2 (board V4-Pagina-Personas): the page of the people of a center on their phone — each block of 5.7 folded with
// its parts under their headings, and the results in plain words: who answered and what most needs attention, for the whole
// center above the minimum (criterio C-54). Nothing of a person, ever.

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 20);
    workIn($this->tenant);
    $this->token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();
});

it('gives each block written with questions its parts under their headings', function (): void {
    $document = resolve(SaveWriting::class)->handle(resolve(Templates::class)->template(DocumentKind::PreventionMeasures), DocumentKind::PreventionMeasures, ['training' => 'yearly', 'workload' => 'quarter', 'actions' => ['breaks']], 3, $this->hr->id);
    resolve(PublishContent::class)->handle($document->id, null, $this->hr->id);

    $this->get(route('workers.center', [$this->token, $this->plant->id]))->assertInertia(fn (Assert $page) => $page
        ->where('blocks.c.sections', [
            ['heading' => 'Capacitación', 'text' => 'Capacitamos a todo el personal sobre los riesgos psicosociales una vez al año.'],
            ['heading' => 'Cargas de trabajo', 'text' => 'Revisamos las cargas de trabajo cada trimestre.'],
            ['heading' => 'Otras medidas', 'text' => 'También hay pausas acordadas por turno.'],
        ]));
});

it('says in plain words what most needs attention: at most three themes, of the whole center', function (): void {
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7, 'Ventas' => 6]);
    $campaign->update(['work_center_id' => $this->plant->id, 'closed_at' => '2026-11-10 18:00:00']);
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $this->get(route('workers.center', [$this->token, $this->plant->id]))->assertInertia(fn (Assert $page) => $page
        ->where('results.published.answered', 13)
        ->where('results.published.themes', fn ($themes): bool => count($themes) >= 1 && count($themes) <= 3));
});

it('names no theme below the minimum', function (): void {
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 4]);
    $campaign->update(['work_center_id' => $this->plant->id, 'closed_at' => '2026-11-10 18:00:00']);
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $this->get(route('workers.center', [$this->token, $this->plant->id]))->assertInertia(fn (Assert $page) => $page
        ->where('results.published', ['state' => 'below_minimum', 'closedOn' => '10 nov 2026']));
});
