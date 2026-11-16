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

it('keeps the 40 people the roster reserves for the door, always in the same order', function (): void {
    $pool = resolve(DemoDoorPool::class);
    $reserved = array_values(array_filter(DemoRoster::people(), fn ($person): bool => $person->reservedForDoor));

    expect($pool)->toBeInstanceOf(RosterDoorPool::class)
        ->and($pool->employeeIds())->toHaveCount(40)
        ->and($pool->employeeIds())->toBe(array_map(fn ($person): string => DemoRoster::employeeId($person->key), $reserved))
        ->and(array_unique($pool->employeeIds()))->toHaveCount(40);
});

// The only door test that prepares the demo (most of a minute): the rest use a small company and a pool of their own.
it('hands out, from the prepared demo, 40 active people of the demo company, each with a portal', function (): void {
    Bus::fake();
    resolve(DemoWorkspace::class)->prepare();
    workIn(Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id()));

    $people = resolve(Roster::class)->findMany(resolve(DemoDoorPool::class)->employeeIds());
    $portals = array_filter(array_map(fn (string $id): ?string => resolve(PortalAccess::class)->url($id), resolve(DemoDoorPool::class)->employeeIds()));

    expect($people)->toHaveCount(40)
        ->and(array_filter($people, fn ($person): bool => $person->active))->toHaveCount(40)
        ->and(array_unique($portals))->toHaveCount(40);
});
