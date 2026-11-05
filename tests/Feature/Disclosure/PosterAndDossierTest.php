<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Support\QrCode;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(QrCode::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme Industrial', 'legal_name' => 'Acme Industrial S.A. de C.V.']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
});

it('draws the QR of an address as an SVG data URI that the policy of the pages allows', function (): void {
    $uri = resolve(QrCode::class)->dataUri('https://humetria.test/workers/abc');

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and((string) base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true))->toContain('<svg')->toContain('<path')
        ->and(resolve(QrCode::class)->dataUri('https://humetria.test/workers/abc'))->toBe($uri)
        ->and(resolve(QrCode::class)->dataUri('https://humetria.test/workers/xyz'))->not->toBe($uri);
});

it('shows the poster with the company, the link in large text and its QR, and audits that it was seen without the token', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $url = route('workers.show', (string) $link->token());

    $this->actingAs($this->hr)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/poster')
            ->where('company', 'Acme Industrial')
            ->where('url', $url)
            ->where('qr', resolve(QrCode::class)->dataUri($url))
            ->where('active', true));

    $entry = AuditEntry::query()->where('event', 'disclosure.poster_viewed')->sole();
    expect($entry->subject_id)->toBe($link->id)->and(json_encode($entry->metadata))->not->toContain((string) $link->token());
});

it('warns on the poster that the page is not active, and gives a new address after the link is rotated', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $old = route('workers.show', (string) $link->token());
    resolve(App\Modules\Disclosure\Actions\DisablePageLink::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('active', false));

    resolve(App\Modules\Disclosure\Actions\RotatePageLink::class)->handle($this->hr->id);
    $this->actingAs($this->hr)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('url', fn ($url) => $url !== $old));
});

it('has no poster before the link exists or when its key was dropped: a 404', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertNotFound();

    resolve(IssuePageLink::class)->handle($this->hr->id);
    PageLink::query()->update(['token_hash' => hash('sha256', 'gone')]);
    $this->actingAs($this->hr)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertNotFound();
});

it('gives the poster and the dossier only to whoever manages', function (TenantRole $role): void {
    resolve(IssuePageLink::class)->handle($this->hr->id);
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.disclosure.page.poster', $this->tenant))->assertForbidden();
    $this->actingAs($user)->get(route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]))->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('prints the dossier of a center: its requirements, its policy, its means and its link, with counts and without names', function (): void {
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id, 'covers' => ['a', 'b']]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);
    $people = Employee::factory()->count(3)->sequence(fn ($sequence) => ['email' => "secreta{$sequence->index}@acme.test"])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Persona Secreta']);
    foreach ($people as $index => $person) {
        Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id, ...($index === 0 ? ['channel' => 'manual', 'acknowledged_at' => now(), 'note' => 'Nota privada de la hoja', 'recorded_by' => $this->hr->id] : [])]);
    }
    DisseminationRecord::factory()->covering(['a', 'd'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'note' => 'Cartel del comedor', 'done_on' => '2026-11-05']);
    resolve(IssuePageLink::class)->handle($this->hr->id);

    $response = $this->actingAs($this->hr)->get(route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('disclosure/dossier')
        ->where('header', ['company' => 'Acme Industrial', 'legalName' => 'Acme Industrial S.A. de C.V.', 'workCenter' => 'Planta Norte', 'issuedOn' => '20 nov 2026'])
        ->where('policy', fn ($p) => $p['version'] === 1 && $p['covers'] === ['a', 'b'] && isset($p['publishedOn']))
        ->where('acknowledgments', ['active' => 3, 'confirmed' => 1])
        ->where('records', fn ($rows) => count($rows) === 1 && $rows[0]['mediumLabel'] === 'Cartel' && $rows[0]['items'] === ['a', 'd'] && $rows[0]['doneOn'] === '5 nov 2026' && $rows[0]['note'] === 'Cartel del comedor')
        ->where('link', fn ($link) => $link['state']['value'] === 'active' && $link['createdOn'] === '20 nov 2026' && ! isset($link['url']))
        ->where('requirements', fn ($rows) => collect($rows)->pluck('code')->all() === ['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8'])
        ->where('note', 'Este documento lista lo registrado en la plataforma; no certifica el cumplimiento de la norma. Los acuses van como conteo: el detalle de quiénes confirmaron está dentro de la aplicación.'));
    $json = (string) $response->getContent();
    expect($json)->not->toContain('Persona Secreta')->not->toContain('secreta0@acme.test')->not->toContain('secreta1@acme.test')->not->toContain('Nota privada')->not->toContain((string) PageLink::query()->sole()->token());
});

it('says where each block of 5.7 comes from and when it was published', function (): void {
    $draft = resolve(App\Modules\Disclosure\Actions\StartContentDraft::class)->handle(App\Modules\Disclosure\Enums\DocumentKind::HealthEffects, null);
    resolve(App\Modules\Disclosure\Actions\PublishContent::class)->handle($draft->id, [$this->plant->id], $this->hr->id);
    App\Modules\Complaints\Models\ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();

    $this->actingAs($this->hr)->get(route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page->where('contents', fn ($blocks) => collect($blocks)->firstWhere('inciso', 'f')['sources'] === [['origin' => 'Texto de RH', 'publishedOn' => '20 nov 2026']]
            && collect($blocks)->firstWhere('inciso', 'd')['sources'] === [['origin' => 'Del canal de quejas', 'publishedOn' => null]]
            && collect($blocks)->firstWhere('inciso', 'b')['sources'] === []));
});

it('says which evaluation was published and when', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    [$campaign] = closedEvaluationInAreas($this->tenant, ['Área 1' => 12]);
    $campaign->update(['work_center_id' => $this->plant->id, 'closed_at' => '2026-11-10 18:00:00']);
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page->where('results', ['closedOn' => '10 nov 2026', 'publishedOn' => '20 nov 2026', 'state' => 'available']));
});

it('audits that the dossier was seen, with the center and no names', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]))->assertOk();

    expect(AuditEntry::query()->where('event', 'disclosure.dossier_viewed')->sole()->metadata)->toEqual(['work_center_id' => $this->plant->id]);
});

it('does not find the dossier of a center of another company, an archived one or one that does not exist', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    foreach ([$foreign->id, $archived->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        $this->actingAs($this->hr)->get(route('tenant.disclosure.dossier', [$this->tenant, $id]))->assertNotFound();
    }
    expect(AuditEntry::query()->where('event', 'disclosure.dossier_viewed')->count())->toBe(0);
});

// Board V3-Inspeccion: «Solo lo que tienes», to hand to the inspector.
it('keeps only the requirements the center meets when asked for what it has', function (): void {
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id, 'covers' => ['a', 'b', 'c']]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);
    $url = route('tenant.disclosure.dossier', [$this->tenant, $this->plant->id]);

    $this->actingAs($this->hr)->get($url.'?ready=1')->assertInertia(fn (Assert $page) => $page
        ->where('requirements', fn ($rows): bool => collect($rows)->pluck('code')->all() === ['D1', 'D2']));
    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('requirements', fn ($rows): bool => count($rows) > 2));
});
