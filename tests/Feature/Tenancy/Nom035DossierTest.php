<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\Nom035Dossier;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Nom035Dossier::class);

// Decision 2 of the launch plan: the NOM-035 dossier of a center, ordered as the inspection table, with each row's state and
// detail and a link to the printable evidence of each module. It reads the rows of the route; it never reads a table of another
// module and never carries a name or an answer.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-04 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficina', 'street' => 'Av. Reforma', 'exterior_number' => '100']);
    staffOf($this->tenant, $this->center, 30);
    $this->closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'closed_at' => '2026-11-02 18:00:00']);
});

function nom035Dossier(array $query = [], ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.nom035.dossier', [test()->tenant, ($center ?? test()->center)->id, ...$query]));
}

it('prints the six rows of the inspection table, in order, with their state and detail', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Dossier()->assertOk()->assertInertia(fn (Assert $page) => $page->component('tenancy/nom035-dossier')
        ->where('center.id', $this->center->id)
        ->where('center.name', 'Oficina')
        ->where('center.address', fn (string $address): bool => str_contains($address, 'Av. Reforma'))
        ->where('issuedOn', '4 nov 2026')
        ->where('readyOnly', false)
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('numeral')->all() === ['5.1', '7.1 y 7.9', '5.5 y 5.8 c', '7.8', '8.3 a 8.5', '7.7'])
        ->where('rows.0', [
            'numeral' => '5.1',
            'title' => 'Política de prevención por escrito',
            'state' => 'missing',
            'detail' => 'Aún no hay una política vigente en este centro',
            'fact' => null,
            'evidence' => null,
        ])
        ->where('rows.1.state', 'done')
        ->where('rows.1.detail', 'Cerrada el 2 nov 2026: contestaron 0 de 0')
        ->where('rows.1.evidence', ['label' => 'Resultados de la evaluación', 'href' => route('tenant.campaigns.results', [$this->tenant, $this->closed->id])])
        ->where('rows.4.state', 'not_applicable'));
});

it('links the printable evidence of each module only for the rows it has', function (): void {
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    ReportDetails::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $disclosure = route('tenant.disclosure.dossier', [$this->tenant, $this->center->id]);

    nom035Dossier()->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.evidence', ['label' => 'Expediente de difusión', 'href' => $disclosure])
        ->where('rows.3.evidence', ['label' => 'Expediente de difusión', 'href' => $disclosure])
        ->where('rows.5.evidence', ['label' => 'Informe 7.7', 'href' => route('tenant.campaigns.report', [$this->tenant, $this->closed->id])]));
});

// R2-nom035-02: a row that stands prints the datum of its record, so the paper is not an index of links.
it('prints one datum per requirement that stands: date, version and counts', function (): void {
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    ReportDetails::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Dossier()->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.fact', fn (string $fact): bool => (bool) preg_match('/^Versión \d+ · publicada el \d{1,2} \w{3} 2026 · 0 de 30 personas la leyeron$/u', $fact))
        ->where('rows.1.fact', fn (string $fact): bool => str_starts_with($fact, 'Vigente hasta el '))
        ->where('rows.2.fact', 'Nadie espera valoración clínica')
        ->where('rows.3.fact', fn (string $fact): bool => str_starts_with($fact, 'Resultados publicados el '))
        ->where('rows.5.fact', 'Informe con conclusiones, recomendaciones y responsable'));
});

it('keeps only what the center can show with ?ready=1', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Dossier(['ready' => 1])->assertInertia(fn (Assert $page) => $page
        ->where('readyOnly', true)
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('state')->unique()->all() === ['done'] && collect($rows)->pluck('numeral')->all() === ['7.1 y 7.9', '5.5 y 5.8 c']));
});

it('says «no aplica» for what a center of up to 15 people does not answer', function (): void {
    $small = WorkCenter::factory()->for($this->tenant)->create();
    staffOf($this->tenant, $small, 15);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Dossier(center: $small)->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->slice(3)->every(fn (array $row): bool => $row['state'] === 'not_applicable' && $row['detail'] === 'No aplica: hasta 15 personas solo contestan la Guía I' && $row['evidence'] === null)));
});

it('links the Guía I channeling as evidence once nobody waits, and never to whoever may not see it', function (TenantRole $role, int $waiting, ?string $detail, bool $linked): void {
    TraumaScreening::factory()->count($waiting)->create(['campaign_id' => $this->closed->id, 'requires_clinical_assessment' => true]);
    $this->actingAs(memberOf($this->tenant, $role));

    nom035Dossier()->assertInertia(fn (Assert $page) => $page
        ->where('rows.2.detail', $detail)
        ->where('rows.2.evidence', $linked ? ['label' => 'Canalización', 'href' => route('tenant.campaigns.screenings', [$this->tenant, $this->closed->id])] : null));
})->with([
    'HR, nobody waits' => [TenantRole::Hr, 0, null, true],
    'HR, two wait' => [TenantRole::Hr, 2, '2 personas esperan valoración clínica', false],
    'a viewer' => [TenantRole::Viewer, 2, 'Solo lo ve quien puede ver la Guía I', false],
]);

it('finds only the centers of the company, and only for whoever is signed in', function (): void {
    nom035Dossier()->assertRedirect(route('login'));

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    nom035Dossier(center: WorkCenter::factory()->create())->assertNotFound();
    nom035Dossier(center: WorkCenter::factory()->for($this->tenant)->archived()->create())->assertNotFound();
});

it('carries no answer, no name and no moment of answer', function (): void {
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->closed->id, 'requires_clinical_assessment' => true]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));

    nom035Dossier()->assertInertia(function (Assert $page): void {
        $props = $page->toArray()['props'];

        expect(array_keys($props))->toContain('center', 'issuedOn', 'readyOnly', 'rows')
            ->and(collect($props['rows'])->every(fn (array $row): bool => array_keys($row) === ['numeral', 'title', 'state', 'detail', 'fact', 'evidence']))->toBeTrue()
            ->and(json_encode($props))->not->toContain('answers')->not->toContain('answered_at');
    });
});

it('records in the audit trail that the dossier was opened', function (): void {
    $this->actingAs($hr = memberOf($this->tenant, TenantRole::Hr));

    nom035Dossier()->assertOk();

    $entry = AuditEntry::query()->where('event', 'nom035.dossier_viewed')->sole();
    expect($entry->actor_id)->toBe($hr->id)->and($entry->metadata)->toEqual(['work_center_id' => $this->center->id, 'ready_only' => false]);
});
