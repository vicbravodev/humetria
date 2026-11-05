<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Seating\Data\SeatingDeclaration;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Support\CompanySeatingApplicability;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

mutates(CompanySeatingApplicability::class);

// Rediseño v2 (boards V2-Aplica-Otras and V3-Onboarding, decisión A2): whoever sets up the company answers, for each center,
// whether someone works standing and, if so, its sector. Nobody standing needs no sector.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('records that someone works standing with the sector, through the declaration Ley Silla keeps', function (): void {
    app(SeatingApplicability::class)->declare($this->center->id, true, 'commerce', $this->hr->id);

    $profile = SeatingProfile::query()->sole();

    expect($profile->no_standing_positions)->toBeFalse()
        ->and($profile->sector)->toBe(Sector::Commerce)
        ->and($profile->declared_by)->toBe($this->hr->id)
        ->and($profile->declared_on->toDateString())->toBe('2026-03-01')
        ->and(AuditEntry::query()->where('event', 'seating.profile_declared')->count())->toBe(1);
});

it('records that nobody works standing without a sector, keeping the sector and the note declared before', function (): void {
    app(SeatingApplicability::class)->declare($this->center->id, false, null, $this->hr->id);

    expect(SeatingProfile::query()->sole()->sector)->toBeNull();

    SeatingProfile::query()->sole()->update(['sector' => Sector::Services, 'note' => 'Solo oficinas.']);
    app(SeatingApplicability::class)->declare($this->center->id, false, null, $this->hr->id);
    $profile = SeatingProfile::query()->sole();

    expect($profile->no_standing_positions)->toBeTrue()
        ->and($profile->sector)->toBe(Sector::Services)
        ->and($profile->note)->toBe('Solo oficinas.');
});

it('reads what each center declared, leaving out the ones that did not and other companies', function (): void {
    $offices = WorkCenter::factory()->for($this->tenant)->create();
    $quiet = WorkCenter::factory()->for($this->tenant)->create();
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => Sector::Industrial, 'declared_on' => '2026-02-03']);
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id, 'sector' => null, 'no_standing_positions' => true, 'declared_on' => '2026-02-04']);
    $theirs = SeatingProfile::factory()->create();

    expect(app(SeatingApplicability::class)->declarations([$this->center->id, $offices->id, $quiet->id, $theirs->work_center_id]))->toEqual([
        $this->center->id => new SeatingDeclaration(true, 'industrial', '3 feb 2026'),
        $offices->id => new SeatingDeclaration(false, null, '4 feb 2026'),
    ]);
});

it('lists the sectors a center may declare', function (): void {
    expect(app(SeatingApplicability::class)->sectors())->toBe(['services', 'commerce', 'analogous', 'industrial']);
});

it('asks the database for a sector wherever someone works standing', function (): void {
    expect(fn () => DB::transaction(fn () => SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => null])))
        ->toThrow(QueryException::class, 'seating_profiles_sector_when_standing');
});
