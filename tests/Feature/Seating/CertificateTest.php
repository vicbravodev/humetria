<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

// Rediseño v2 (decisiones A2 y D6): where Ley Silla does not apply, the center keeps a certificate to print, with the day,
// the center, the standard and who declared it. A page with print CSS, never a PDF.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $this->author = User::factory()->create(['name' => 'Rita Ramírez']);
});

function seatingCertificate(): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.seating.certificate', [test()->tenant, test()->center->id]));
}

it('certifies that Ley Silla does not apply, with the day, the center and who declared it', function (TenantRole $role): void {
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => null, 'no_standing_positions' => true, 'declared_by' => $this->author->id, 'declared_on' => '2026-02-03']);

    $this->actingAs(memberOf($this->tenant, $role));

    seatingCertificate()->assertInertia(fn (Assert $page) => $page->component('seating/certificate')
        ->where('workCenter.id', $this->center->id)
        ->where('workCenter.name', 'Oficinas Monterrey')
        ->has('workCenter.legalName')
        ->has('workCenter.address')
        ->where('declaredOn', '3 de febrero de 2026')
        ->where('declaredBy', 'Rita Ramírez'));
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer]);

it('has no certificate where someone works standing, a position was registered since, or nothing was said', function (string $case): void {
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];

    match ($case) {
        'someone works standing' => SeatingProfile::factory()->create($where),
        'a position since' => [SeatingProfile::factory()->create([...$where, 'no_standing_positions' => true]), StandingPosition::factory()->create($where)],
        'never answered' => null,
    };

    $this->actingAs(ownerOf($this->tenant));

    seatingCertificate()->assertNotFound();
})->with(['someone works standing', 'a position since', 'never answered']);

it('answers 404 for a center of another company', function (): void {
    $theirs = SeatingProfile::factory()->create(['no_standing_positions' => true]);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.seating.certificate', [$this->tenant, $theirs->work_center_id]))
        ->assertNotFound();
});
