<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\RosterDoorPool;
use Illuminate\Support\Facades\Bus;

it('finds nobody for the door in a company without the demo\'s people', function (): void {
    workIn(Tenant::factory()->create());

    expect(resolve(DemoDoorPool::class))->toBeInstanceOf(RosterDoorPool::class)
        ->and(resolve(DemoDoorPool::class)->employeeIds())->toBe([]);
});

// The only door test that prepares the demo (most of a minute): the rest use a small company and a pool of their own.
it('hands out, from the prepared demo, 40 active people of the demo company, each with a portal', function (): void {
    Bus::fake();
    resolve(DemoWorkspace::class)->prepare();
    workIn(Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id()));

    // The 40 the roster reserves, always in its order, read from the company: their ids are new on every prepare.
    $reserved = array_values(array_filter(DemoRoster::people(), fn ($person): bool => $person->reservedForDoor));
    expect(resolve(DemoDoorPool::class)->employeeIds())->toBe(array_map(fn ($person): string => DemoRoster::employeeId($person->key), $reserved))
        ->and(array_unique(resolve(DemoDoorPool::class)->employeeIds()))->toHaveCount(40);

    $people = resolve(Roster::class)->findMany(resolve(DemoDoorPool::class)->employeeIds());
    $portals = array_filter(array_map(fn (string $id): ?string => resolve(PortalAccess::class)->url($id), resolve(DemoDoorPool::class)->employeeIds()));

    expect($people)->toHaveCount(40)
        ->and(array_filter($people, fn ($person): bool => $person->active))->toHaveCount(40)
        ->and(array_unique($portals))->toHaveCount(40);

    // What a visitor finds on arriving: a survey to answer and the policy to confirm, in the home of the portal (a sample).
    foreach (array_values($portals) as $i => $portal) {
        if ($i % 8 !== 0) {
            continue;
        }

        $sections = collect($this->get($portal)->assertOk()->viewData('page')['props']['sections'])->keyBy('key');
        expect($sections->only(['survey', 'engagement'])->sum('pending'))->toBeGreaterThan(0, "door person #{$i} has no survey pending")
            ->and($sections->get('policy')['pending'] ?? 0)->toBeGreaterThan(0, "door person #{$i} has no policy to confirm");
    }
});
