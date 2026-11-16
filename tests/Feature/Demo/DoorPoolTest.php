<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Contracts\DemoEntrance;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Organization\Contracts\Roster;
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
it('hands out, from the prepared demo, 40 active people of the demo company, each with a way in', function (): void {
    Bus::fake();
    resolve(DemoWorkspace::class)->prepare();
    workIn(Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id()));

    // The 40 the roster reserves, always in its order, read from the company: their ids are new on every prepare.
    $reserved = array_values(array_filter(DemoRoster::people(), fn ($person): bool => $person->reservedForDoor));
    expect(resolve(DemoDoorPool::class)->employeeIds())->toBe(array_map(fn ($person): string => DemoRoster::employeeId($person->key), $reserved))
        ->and(array_unique(resolve(DemoDoorPool::class)->employeeIds()))->toHaveCount(40);

    $ids = resolve(DemoDoorPool::class)->employeeIds();
    $people = resolve(Roster::class)->findMany($ids);
    // The door's way in for each: a one-time link, the kind the demo's head of HR issues (WorkerAccess).
    $entrances = array_map(fn (string $id): string => resolve(DemoEntrance::class)->for($id), $ids);
    $slug = Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id())->slug;

    expect($people)->toHaveCount(40)
        ->and(array_filter($people, fn ($person): bool => $person->active))->toHaveCount(40)
        ->and(array_unique($entrances))->toHaveCount(40);

    // What a visitor finds on arriving: a survey to answer and the policy to confirm, in the start of the portal (a sample).
    foreach ($entrances as $i => $entrance) {
        if ($i % 8 !== 0) {
            continue;
        }

        $this->post($entrance)->assertRedirect(route('worker.key.show', $slug));
        $sections = collect($this->get(route('worker.home', $slug))->assertOk()->viewData('page')['props']['sections'])->keyBy('key');
        expect($sections->only(['survey', 'engagement'])->sum('pending'))->toBeGreaterThan(0, "door person #{$i} has no survey pending")
            ->and($sections->get('policy')['pending'] ?? 0)->toBeGreaterThan(0, "door person #{$i} has no policy to confirm");
    }
});
