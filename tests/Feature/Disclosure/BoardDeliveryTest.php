<?php

declare(strict_types=1);

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\CenterFacts;
use App\Modules\Disclosure\Support\Requirements;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Requirements::class, CenterFacts::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
    $this->policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'work_center_id' => $this->plant->id]);
});

/** The D3 row of the plant, or null when the board does not judge it. @return array<string, mixed>|null */
function deliveryRow(object $test): ?array
{
    $row = null;
    $test->actingAs($test->hr)->get(route('tenant.disclosure.index', $test->tenant))
        ->assertInertia(function (Assert $page) use (&$row): void {
            $row = collect($page->toArray()['props']['centers']['data'][0]['requirements'])->firstWhere('code', 'D3');
        });

    return $row;
}

function posterAt(object $test, WorkCenter $center, array $items = ['a']): void
{
    DisseminationRecord::factory()->covering($items)->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $center->id]);
}

function askedOfPlant(object $test, int $people, int $confirmed, ?Document $version = null): void
{
    foreach (range(1, $people) as $number) {
        $person = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id]);
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => ($version ?? $test->policy)->id, 'employee_id' => $person->id, ...($number <= $confirmed ? ['channel' => 'portal', 'acknowledged_at' => now()] : [])]);
    }
}

it('is missing while the policy is not in front of the people', function (): void {
    expect(deliveryRow($this)['state'])->toBe(['value' => 'missing', 'label' => 'Falta'])
        ->and(deliveryRow($this)['numeral'])->toBe('5.1, 5.7 a')
        ->and(deliveryRow($this)['detail'])->toBe('La política no está a la vista de las personas: publique la página o registre un medio físico (cartel, boletín, folleto).');
});

it('counts a physical means of that center that covers the policy as delivery', function (): void {
    posterAt($this, $this->plant, ['a', 'd']);

    expect(deliveryRow($this)['state']['value'])->toBe('met');
});

it('does not count a means that covers other incisos, or one of another center', function (array $items, bool $elsewhere): void {
    $other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Zona Sur']);
    posterAt($this, $elsewhere ? $other : $this->plant, $items);

    expect(deliveryRow($this)['state']['value'])->toBe('missing');
})->with([
    'covering b to f' => [['b', 'c', 'd', 'e', 'f'], false],
    'covering a in another center' => [['a'], true],
]);

it('is met when every active person confirmed, with how many', function (): void {
    posterAt($this, $this->plant);
    askedOfPlant($this, 2, 2);

    expect(deliveryRow($this))->toMatchArray(['state' => ['value' => 'met', 'label' => 'Cumple'], 'detail' => '2 de 2 personas confirmaron.']);
});

it('warns while some have not confirmed, saying the norm does not ask for it', function (): void {
    posterAt($this, $this->plant);
    askedOfPlant($this, 3, 1);

    expect(deliveryRow($this))->toMatchArray(['state' => ['value' => 'notice', 'label' => 'Con aviso'], 'detail' => '1 de 3 personas confirmaron; la norma no pide el acuse.']);
});

it('counts only people active today and only the version in force', function (): void {
    posterAt($this, $this->plant);
    askedOfPlant($this, 2, 2);
    $left = Employee::query()->first();
    $left?->update(['deactivated_at' => now()]);
    $newer = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_by' => $this->hr->id]);
    // Version 2 reached no center yet: the one in force for the plant is still the first.
    expect(deliveryRow($this)['detail'])->toBe('1 de 1 persona confirmó.');

    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $newer->id, 'work_center_id' => $this->plant->id]);
    askedOfPlant($this, 2, 0, $newer);

    expect(deliveryRow($this))->toMatchArray(['detail' => '0 de 3 personas confirmaron; la norma no pide el acuse.'])
        ->and(deliveryRow($this)['state']['value'])->toBe('notice');
});

it('is met with nobody to ask, and says nothing of acknowledgments', function (): void {
    posterAt($this, $this->plant);

    expect(deliveryRow($this))->toMatchArray(['detail' => null])
        ->and(deliveryRow($this)['state']['value'])->toBe('met');
});

it('does not judge delivery of a center without policy', function (): void {
    $bare = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Aaa Sin Política']);
    posterAt($this, $bare);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.requirements', fn ($rows) => collect($rows)->pluck('code')->all() === ['D1', 'D4', 'D5', 'D6', 'D7', 'D8']));
});
