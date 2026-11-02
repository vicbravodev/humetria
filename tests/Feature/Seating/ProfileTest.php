<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(DeclareProfile::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function declareSector(array $data = [], ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->put(route('tenant.seating.profile.update', [test()->tenant, ($center ?? test()->center)->id]), [
        'sector' => 'services',
        'no_standing_positions' => false,
        'note' => null,
        ...$data,
    ]);
}

it('records the sector with who and when and audits it', function (): void {
    $this->actingAs($this->hr);

    declareSector(['sector' => 'commerce', 'note' => 'Tienda y bodega.'])->assertRedirect()->assertSessionHasNoErrors();

    $profile = SeatingProfile::query()->sole();
    $entry = AuditEntry::query()->where('event', 'seating.profile_declared')->sole();

    expect($profile->work_center_id)->toBe($this->center->id)
        ->and($profile->sector)->toBe(Sector::Commerce)
        ->and($profile->declared_by)->toBe($this->hr->id)
        ->and($profile->declared_on->toDateString())->toBe('2026-03-01')
        ->and($profile->no_standing_positions)->toBeFalse()
        ->and($profile->note)->toBe('Tienda y bodega.')
        ->and($entry->actor_id)->toBe($this->hr->id)
        ->and($entry->metadata)->toEqual(['work_center_id' => $this->center->id, 'sector' => 'commerce', 'no_standing_positions' => false]);
});

it('keeps one profile per center: declaring again replaces the sector and the date', function (): void {
    $this->actingAs($this->hr);
    declareSector(['sector' => 'services'])->assertSessionHasNoErrors();
    $this->travelTo('2026-04-10 12:00:00');

    declareSector(['sector' => 'industrial', 'no_standing_positions' => true])->assertSessionHasNoErrors();

    $profile = SeatingProfile::query()->sole();
    expect($profile->sector)->toBe(Sector::Industrial)
        ->and($profile->no_standing_positions)->toBeTrue()
        ->and($profile->declared_on->toDateString())->toBe('2026-04-10');
});

it('rejects an unknown sector, a missing one and a note that is too long', function (array $data, string $field): void {
    $this->actingAs($this->hr);

    declareSector($data)->assertSessionHasErrors($field);

    expect(SeatingProfile::query()->count())->toBe(0);
})->with([
    'unknown sector' => [['sector' => 'mining'], 'sector'],
    'missing sector' => [['sector' => null], 'sector'],
    'long note' => [['note' => str_repeat('a', 1001)], 'note'],
]);

it('lets only manage-seating declare the sector', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role));

    declareSector()->assertForbidden();

    expect(SeatingProfile::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('refuses an archived center, a made-up one and a center id that is not a uuid', function (): void {
    $this->actingAs($this->hr);
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    declareSector([], $archived)->assertNotFound();
    $form = ['sector' => 'services', 'no_standing_positions' => false];
    $this->put(route('tenant.seating.profile.update', [$this->tenant, (string) Illuminate\Support\Str::uuid()]), $form)->assertNotFound();
    $this->put(route('tenant.seating.profile.update', [$this->tenant, 'nope']), $form)->assertNotFound();
});

it('has seating.profile_declared logs with ids only', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    declareSector(['sector' => 'analogous', 'note' => 'Texto de la nota'])->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->with('seating.profile_declared', ['work_center_id' => $this->center->id, 'sector' => 'analogous', 'no_standing_positions' => false])->once();
});

it('refuses in the database a second profile per center and an unknown sector', function (): void {
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    expect(fn () => DB::transaction(fn () => SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])))
        ->toThrow(QueryException::class, 'seating_profiles_work_center_unique')
        ->and(fn () => DB::transaction(fn () => DB::table('seating_profiles')->update(['sector' => 'mining'])))
        ->toThrow(QueryException::class, 'seating_profiles_sector_known');
});

it('does not let the committee declare even in its own center', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $this->actingAs($cora);

    declareSector()->assertForbidden();
});
