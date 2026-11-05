<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

// Rediseño v2 (board V2-N037-Docs, decisión A3): besides the policy, the six documents NOM-037 asks for, each written with
// questions or uploaded, made once for every center.

beforeEach(function (): void {
    $this->travelTo('2026-10-12 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->north = WorkCenter::factory()->for($this->tenant)->create();
    $this->south = WorkCenter::factory()->for($this->tenant)->create();
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function supportingPage(): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.telework.supporting.index', test()->tenant));
}

it('lists the six supporting documents in the order of the norm, with what each is and how long it takes', function (): void {
    $this->actingAs($this->hr);

    supportingPage()->assertInertia(fn (Assert $page) => $page->component('telework/supporting-documents')
        ->where('centers', 2)
        ->where('canManage', true)
        ->has('documents', 6)
        ->where('documents.0', [
            'kind' => 'risk_information', 'name' => 'Riesgos de trabajar desde casa', 'summary' => 'Qué riesgos tiene el teletrabajo y cómo evitarlos. Se entrega a cada persona.',
            'numeral' => '5.3', 'minutes' => 5, 'ready' => false, 'started' => false, 'version' => null,
        ])
        ->where('documents.5.kind', 'committee_participation')
        ->where('documents.5.numeral', '5.14'));
});

it('marks a document ready once a version is in force in every center in use, uploaded or written', function (): void {
    $create = fn (WorkCenter $center, DocumentKind $kind, int $version) => TeleworkDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'kind' => $kind, 'version' => $version]);
    $create($this->north, DocumentKind::CommitteeParticipation, 1);
    $this->travel(1)->day();
    $latest = $create($this->south, DocumentKind::CommitteeParticipation, 2);
    $create($this->north, DocumentKind::Reversibility, 1);
    DocumentDraft::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => DocumentKind::FamilyViolence]);

    $this->actingAs($this->hr);

    supportingPage()->assertInertia(fn (Assert $page) => $page
        ->where('documents.5.ready', true)
        ->where('documents.5.version', ['id' => $latest->id, 'number' => 2, 'publishedOn' => '13 oct 2026'])
        ->where('documents.3.ready', false)
        ->where('documents.4.started', true)
        ->where('documents.4.ready', false));
});

it('lets the people who only read NOM-037 see it, without the ways to write or upload', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    supportingPage()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->has('documents', 6));
});

it('leads a supporting document back to its list, and the policy back to the centers', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.telework.writing.show', [$this->tenant, 'risk_information']))
        ->assertInertia(fn (Assert $page) => $page->where('back', ['href' => route('tenant.telework.supporting.index', $this->tenant), 'label' => 'Guardar y volver', 'center' => null]));

    $this->get(route('tenant.telework.writing.show', [$this->tenant, 'policy']))
        ->assertInertia(fn (Assert $page) => $page->where('back.href', route('tenant.telework.index', $this->tenant)));
});

it('opens the upload of the documents page on the kind chosen with «Ya lo tengo»', function (): void {
    $this->actingAs($this->hr);

    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'kind' => 'reversibility']))
        ->assertInertia(fn (Assert $page) => $page->where('chosenKind', 'reversibility'));
    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'kind' => 'nope']))
        ->assertInertia(fn (Assert $page) => $page->where('chosenKind', null));
});
