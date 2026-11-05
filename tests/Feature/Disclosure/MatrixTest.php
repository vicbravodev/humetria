<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\Matrix;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Matrix::class);

// Rediseño v2 (board V4-Difusion): «Lo que tus personas deben conocer», the six things of 5.7 (a to f) by center in one matrix
// instead of six tabs, how many are ready and the one next step (criterio C-52). States and counts only, never a person.

beforeEach(function (): void {
    $this->travelTo('2026-11-05 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    staffOf($this->tenant, $this->north, 20);
    staffOf($this->tenant, $this->south, 3);
});

function matrix(): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.disclosure.index', test()->tenant));
}

/** A version of `$kind` published to `$centers`. */
function publishedTo(DocumentKind $kind, array $centers, int $version = 1): Document
{
    $document = Document::factory()->published()->create([
        'tenant_id' => test()->tenant->id,
        'kind' => $kind,
        'version' => $version,
        'covers' => $kind === DocumentKind::Policy ? ['a', 'b', 'c'] : null,
        'content' => [['key' => 'body', 'heading' => $kind->label(), 'text' => 'Texto', 'items' => [], 'list_style' => null]],
    ]);

    foreach ($centers as $center) {
        DocumentCenter::query()->create(['tenant_id' => test()->tenant->id, 'document_id' => $document->id, 'work_center_id' => $center->id]);
    }

    return $document;
}

it('lays out the six things the people must know, in the order of the board, each with a state per center', function (): void {
    $this->actingAs($this->hr);

    matrix()->assertInertia(fn (Assert $page) => $page->component('disclosure/index')
        ->where('items', fn ($items): bool => collect($items)->pluck('key')->all() === ['a', 'd', 'b', 'c', 'f', 'e']
            && collect($items)->pluck('numeral')->all() === ['5.1', '5.7 d', '5.7 b', '5.7 c', '5.7 f', '7.8'])
        ->where('items.2.label', 'Medidas contra la violencia laboral')
        ->where('items.2.caption', 'Qué se considera violencia, qué pasa al denunciar y cómo se protege a quien denuncia')
        ->where('items.2.action', ['label' => 'Escribir · 5 min', 'href' => route('tenant.disclosure.write', [$this->tenant, 'violence_measures']), 'primary' => false])
        ->where('items.0.action', ['label' => 'Escribir · 10 min', 'href' => route('tenant.disclosure.write', [$this->tenant, 'policy']), 'primary' => true])
        ->where('items.4.action.label', 'Revisar borrador')
        ->where('centers.data.0.name', 'Planta Norte')
        ->where('centers.data.0.cells', ['a' => 'missing', 'd' => 'missing', 'b' => 'missing', 'c' => 'missing', 'f' => 'missing', 'e' => 'waiting'])
        // Up to 15 people the results do not apply (num. 2 a).
        ->where('centers.data.1.cells.e', 'not_applicable')
        ->where('progress', ['ready' => 0, 'total' => 6, 'minutes' => 25])
        ->where('reach', ['page' => false, 'confirmed' => 0, 'people' => 0])
        ->where('next', [
            'item' => 'a',
            'title' => 'Escribe tu política de prevención',
            'text' => '6 preguntas con opciones. Al terminar se publica en tus 2 centros y llega a tus personas para que confirmen que la leyeron.',
            'action' => ['label' => 'Empezar', 'href' => route('tenant.disclosure.write', [$this->tenant, 'policy'])],
        ]));
});

it('counts a thing as ready where it is published and in front of the people, and moves the next step on', function (): void {
    $this->actingAs($this->hr);
    resolve(IssuePageLink::class)->handle($this->hr->id);
    publishedTo(DocumentKind::Policy, [$this->north, $this->south]);
    publishedTo(DocumentKind::ViolenceMeasures, [$this->north]);

    matrix()->assertInertia(fn (Assert $page) => $page
        ->where('centers.data.0.cells.a', 'done')
        ->where('centers.data.0.cells.b', 'done')
        ->where('centers.data.1.cells.b', 'missing')
        ->where('items.0.caption', 'Publicada, versión 1 del 5 nov 2026')
        ->where('items.0.action', ['label' => 'Ver', 'href' => route('tenant.disclosure.policy', $this->tenant), 'primary' => false])
        ->where('progress.ready', 1)
        ->where('reach', ['page' => true, 'confirmed' => 0, 'people' => 23])
        ->where('progress.minutes', 15)
        ->where('next.item', 'd')
        ->where('next.title', 'Escribe cómo presentar una queja'));
});

it('says a thing published where the people cannot see it yet, and asks to show it', function (): void {
    $this->actingAs($this->hr);
    publishedTo(DocumentKind::Policy, [$this->north, $this->south]);

    matrix()->assertInertia(fn (Assert $page) => $page
        ->where('centers.data.0.cells.a', 'undelivered')
        ->where('next.item', 'a')
        ->where('next.title', 'Pon a la vista de tus personas lo que publicaste')
        ->where('next.action', ['label' => 'Activar su página', 'href' => route('tenant.disclosure.page', $this->tenant)]));
});

it('marks a thing with a draft under way, and goes on with it', function (): void {
    $this->actingAs($this->hr);
    $this->put(route('tenant.disclosure.write.update', [$this->tenant, 'policy']), ['step' => 2]);

    matrix()->assertInertia(fn (Assert $page) => $page
        ->where('items.0.draft', true)
        ->where('items.0.caption', 'Tienes un borrador a medias')
        ->where('items.0.action.label', 'Continuar')
        ->where('next.action.label', 'Continuar'));
});

it('offers to publish the results of the center whose evaluation closed', function (): void {
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 6]);
    $campaign->update(['work_center_id' => $this->north->id, 'closed_at' => '2026-11-01 18:00:00']);
    $this->actingAs($this->hr);

    matrix()->assertInertia(fn (Assert $page) => $page
        // The factory of the evaluation adds a center of its own: the plant is found by its id, not by its place.
        ->where('centers.data', fn ($centers): bool => collect($centers)->firstWhere('id', $this->north->id)['cells']['e'] === 'ready')
        ->where('items.5.action', ['label' => 'Publicar Planta Norte', 'href' => route('tenant.disclosure.results.share', [$this->tenant, $campaign->id]), 'primary' => false]));
});

it('shows the matrix to whoever only reads it, with no step to take', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    matrix()->assertInertia(fn (Assert $page) => $page
        ->where('items.0.action', null)
        ->where('next', null)
        ->where('can.manage', false));
});

it('pages the centers, the columns of the matrix, ten at a time', function (): void {
    WorkCenter::factory()->for($this->tenant)->count(10)->create();
    $this->actingAs($this->hr);

    matrix()->assertInertia(fn (Assert $page) => $page->where('centers.total', 12)->has('centers.data', 10));
});
