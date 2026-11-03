<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\PublishPolicy;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Http\Controllers\DisclosureController;
use App\Modules\Disclosure\Support\Board;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Disclosure\Support\Requirements;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DisclosureController::class, Board::class, PolicyStanding::class, Requirements::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    workIn($this->tenant);
});

/** Publishes a complete policy to `$centers`. @param list<string>|null $centers @param list<string> $covers */
function publishedPolicy(object $test, ?array $centers, array $covers = ['a', 'b', 'c']): void
{
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    $sections = array_map(fn (array $section): array => ['key' => $section['key'], 'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'Dirección de RH' : $section['text'], 'items' => $section['items']], $draft->content);
    resolve(SaveDraft::class)->handle($draft->id, $sections);
    resolve(PublishPolicy::class)->handle($draft->id, $centers, $covers, true, $test->hr->id);
}

/** The board's rows of a center, by code. @return array<string, array<string, mixed>> */
function boardRows(object $test, string $name): array
{
    $found = [];
    $test->actingAs($test->hr)->get(route('tenant.disclosure.index', $test->tenant))
        ->assertInertia(function (Assert $page) use (&$found, $name): void {
            $center = collect($page->toArray()['props']['centers']['data'])->firstWhere('name', $name);
            $found = array_column($center['requirements'], null, 'code');
        });

    return $found;
}

it('says «Falta» for a center with no policy, and judges nothing else of it', function (): void {
    $rows = boardRows($this, 'Planta Norte');

    expect(array_keys($rows))->toBe(['D1'])
        ->and($rows['D1'])->toMatchArray(['numeral' => '5.1', 'state' => ['value' => 'missing', 'label' => 'Falta'], 'detail' => 'Aún no hay una política publicada para este centro.']);
});

it('is met for a center the policy reached, with its version and day', function (): void {
    publishedPolicy($this, [$this->north->id]);

    $north = boardRows($this, 'Planta Norte');
    $south = boardRows($this, 'Planta Sur');

    expect($north['D1']['state']['value'])->toBe('met')
        ->and($north['D1']['detail'])->toBe('Versión 1, publicada el 20 nov 2026.')
        ->and($north['D2'])->toMatchArray(['numeral' => '5.1 a, b y c', 'state' => ['value' => 'met', 'label' => 'Cumple'], 'detail' => null])
        ->and($south['D1']['state']['value'])->toBe('missing');
});

it('warns when the version in force leaves out some inciso of 5.1', function (array $covers, string $detail): void {
    publishedPolicy($this, null, $covers);

    $rows = boardRows($this, 'Planta Norte');

    expect($rows['D1']['state']['value'])->toBe('met')
        ->and($rows['D2']['state'])->toBe(['value' => 'notice', 'label' => 'Con aviso'])
        ->and($rows['D2']['detail'])->toBe($detail);
})->with([
    'only a' => [['a'], 'La versión 1 no declara los incisos b y c del numeral 5.1.'],
    'a and c' => [['a', 'c'], 'La versión 1 no declara el inciso b del numeral 5.1.'],
    'none' => [[], 'La versión 1 no declara los incisos a, b y c del numeral 5.1.'],
]);

it('takes, for each center, the highest version that reached it', function (): void {
    publishedPolicy($this, null);
    publishedPolicy($this, [$this->south->id], ['a', 'b']);

    $north = boardRows($this, 'Planta Norte');
    $south = boardRows($this, 'Planta Sur');

    expect($north['D1']['detail'])->toBe('Versión 1, publicada el 20 nov 2026.')
        ->and($north['D2']['state']['value'])->toBe('met')
        ->and($south['D1']['detail'])->toBe('Versión 2, publicada el 20 nov 2026.')
        ->and($south['D2']['state']['value'])->toBe('notice');
});

it('leaves a center created after the publication without policy, and an archived one out', function (): void {
    publishedPolicy($this, null);
    WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Nueva']);
    WorkCenter::factory()->for($this->tenant)->archived()->create(['name' => 'Planta Cerrada']);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.total', 3)
            ->where('centers.data', fn ($centers) => collect($centers)->pluck('name')->sort()->values()->all() === ['Planta Norte', 'Planta Nueva', 'Planta Sur'])
            ->where('summary', ['met' => 2, 'notice' => 0, 'missing' => 1]));
});

it('counts every center in the summary and lists them ten at a time, by name', function (): void {
    foreach (range(1, 10) as $number) {
        WorkCenter::factory()->for($this->tenant)->create(['name' => sprintf('Centro %02d', $number)]);
    }
    publishedPolicy($this, [$this->north->id]);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.total', 12)
            ->where('centers.per_page', 10)
            ->has('centers.data', 10)
            ->where('centers.data.0.name', 'Centro 01')
            ->where('summary', ['met' => 1, 'notice' => 0, 'missing' => 11]));

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('centers.data', 2)->where('centers.data.0.name', 'Planta Norte'));
});

it('gives each center the overall state of its worst row', function (): void {
    publishedPolicy($this, [$this->north->id], ['a']);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.data.0.state', ['value' => 'notice', 'label' => 'Con aviso'])
            ->where('centers.data.1.state', ['value' => 'missing', 'label' => 'Falta']));
});

it('does not count the policy of another company', function (): void {
    $other = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($other)->create();
    workIn($other);
    publishedPolicy($this, [$center->id]);
    workIn($this->tenant);

    expect(boardRows($this, 'Planta Norte')['D1']['state']['value'])->toBe('missing');
});

it('shows the board to whoever reads the disclosure and closes it to the rest', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.disclosure.index', $this->tenant))->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 200],
    'committee' => [TenantRole::Committee, 403],
    'complaints' => [TenantRole::Complaints, 403],
]);

it('sends a guest to sign in', function (): void {
    $this->get(route('tenant.disclosure.index', $this->tenant))->assertRedirect(route('login'));
});
