<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyTimezones;
use App\Modules\Tenancy\Support\MexicanTimezones;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CompanyTimezones::class);

/** The one work center in the table, whatever its company. */
function theWorkCenter(): WorkCenter
{
    return WorkCenter::query()->withoutGlobalScopes()->sole();
}

it('keeps what exists in Mexico City: no customers yet, no back-compatibility', function (): void {
    $center = WorkCenter::factory()->create();

    expect($center->fresh()?->timezone)->toBe('America/Mexico_City')
        ->and(Tenant::query()->findOrFail($center->tenant_id)->timezone)->toBe('America/Mexico_City');
});

it('rejects in the database a zone that is not of Mexico', function (string $table, string $constraint, string $zone): void {
    $center = WorkCenter::factory()->create();
    $id = $table === 'tenants' ? $center->tenant_id : $center->id;

    expect(fn () => DB::table($table)->where('id', $id)->update(['timezone' => $zone]))->toThrow(QueryException::class, $constraint);
})->with([
    'a work center' => ['work_centers', 'work_centers_timezone_known'],
    'a company' => ['tenants', 'tenants_timezone_known'],
])->with(['America/Los_Angeles', 'UTC']);

it('chooses the zone by itself from the state and the municipality when none is sent', function (string $state, string $municipality, string $zone): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['state' => $state, 'municipality' => $municipality]))
        ->assertSessionHasNoErrors();

    expect(theWorkCenter()->timezone)->toBe($zone);
})->with([
    'Tijuana' => ['BCN', 'Tijuana', 'America/Tijuana'],
    'Cancún' => ['ROO', 'Benito Juárez', 'America/Cancun'],
    'Hermosillo' => ['SON', 'Hermosillo', 'America/Hermosillo'],
    'Ciudad Juárez' => ['CHH', 'Juárez', 'America/Ciudad_Juarez'],
    'Querétaro' => ['QUE', 'Querétaro', 'America/Mexico_City'],
]);

it('keeps the zone the person chose over the suggested one', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['state' => 'CHH', 'municipality' => 'Chihuahua', 'timezone' => 'America/Ojinaga']))
        ->assertSessionHasNoErrors();

    expect(theWorkCenter()->timezone)->toBe('America/Ojinaga');
});

it('refuses a zone that is not of Mexico, and stores nothing', function (string $zone): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['timezone' => $zone]))
        ->assertSessionHasErrors(['timezone' => 'Elige una zona horaria de la lista.']);

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
})->with(['America/Los_Angeles', 'UTC', 'Hora del Centro', 'america/tijuana']);

it('changes the zone of a work center being corrected', function (): void {
    $tenant = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($tenant)->create(['state' => MexicanState::Queretaro]);

    $this->actingAs(ownerOf($tenant))
        ->put(route('tenant.work-centers.update', [$tenant, $center->id]), workCenterForm(['state' => 'BCN', 'municipality' => 'Mexicali', 'timezone' => 'America/Tijuana']))
        ->assertSessionHasNoErrors();

    expect($center->fresh()?->timezone)->toBe('America/Tijuana');
});

it('shows each work center with its zone and offers the zones and the rules to suggest one', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Tijuana', 'timezone' => 'America/Tijuana']);
    $this->travelTo('2026-12-01 12:00:00');

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workCenters.data.0.timezone', 'America/Tijuana')
            ->where('workCenters.data.0.timezone_label', 'Hora del Noroeste, UTC−8 · Tijuana')
            ->where('timezones', MexicanTimezones::options(now()->toImmutable()))
            ->where('timezoneRules', MexicanTimezones::rules()));
});

it('gives other modules the zone of a work center, archived ones included, and the company\'s', function (): void {
    $tenant = Tenant::factory()->create(['timezone' => 'America/Monterrey']);
    $tijuana = WorkCenter::factory()->for($tenant)->create(['timezone' => 'America/Tijuana']);
    $cancun = WorkCenter::factory()->for($tenant)->archived()->create(['timezone' => 'America/Cancun']);
    $other = WorkCenter::factory()->for(Tenant::factory()->create())->create(['timezone' => 'America/Hermosillo']);
    workIn($tenant);

    $zones = resolve(Timezones::class);

    expect($zones->forTenant())->toBe('America/Monterrey')
        ->and($zones->forCenter($tijuana->id))->toBe('America/Tijuana')
        ->and($zones->forCenter($cancun->id))->toBe('America/Cancun')
        // Another company's center is not found: the company's zone, never the other's.
        ->and($zones->forCenter($other->id))->toBe('America/Monterrey')
        ->and($zones->forCenter('not-a-uuid'))->toBe('America/Monterrey')
        ->and($zones->forCenters([$tijuana->id, $cancun->id, $other->id]))->toBe([
            $tijuana->id => 'America/Tijuana',
            $cancun->id => 'America/Cancun',
            $other->id => 'America/Monterrey',
        ])
        ->and($zones->forCenters([]))->toBe([]);

    $this->travelTo('2026-07-01 12:00:00');

    expect($zones->label('America/Tijuana'))->toBe('Hora del Noroeste, UTC−7 · Tijuana');
});

it('reads the zones of many centers in one query', function (): void {
    $tenant = Tenant::factory()->create();
    $ids = WorkCenter::factory()->for($tenant)->count(3)->create()->modelKeys();
    workIn($tenant);
    $zones = resolve(Timezones::class);
    $zones->forTenant();

    $this->expectsDatabaseQueryCount(1);

    $zones->forCenters($ids);
});
