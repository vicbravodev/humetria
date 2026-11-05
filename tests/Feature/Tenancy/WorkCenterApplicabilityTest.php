<?php

declare(strict_types=1);

use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Actions\RecordApplicability;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\ApplicabilityPages;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RecordApplicability::class, ApplicabilityPages::class);

// Rediseño v2 (board V2-Aplica-Otras, decisión A2): what NOM-035 and Ley Silla ask of one center. NOM-035 applies to every
// center and its guides follow the people registered there; Ley Silla is answered and kept by Ley Silla.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function answerCenters(array $centers): Illuminate\Testing\TestResponse
{
    return test()->from(route('tenant.work-centers.applies', [test()->tenant, test()->center->id]))
        ->put(route('tenant.work-centers.applicability.update', test()->tenant), ['centers' => $centers]);
}

it('tells how many people the center has, which NOM-035 guides that means, and what it declared for Ley Silla', function (int $people, string $range): void {
    staffOf($this->tenant, $this->center, $people);
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => Sector::Commerce, 'declared_on' => '2026-02-03']);

    $this->actingAs($this->hr)
        ->get(route('tenant.work-centers.applies', [$this->tenant, $this->center->id, 'norm' => 'seating']))
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/applies')
            ->where('workCenter', ['id' => $this->center->id, 'name' => 'Oficinas Monterrey'])
            ->where('people', $people)
            ->where('range', $range)
            ->where('norm', 'seating')
            ->where('seating', ['standing' => true, 'sector' => 'commerce', 'declaredOn' => '3 feb 2026']));
})->with([
    'up to 15' => [15, 'up_to_15'],
    '16 to 50' => [16, '16_to_50'],
    'over 50' => [51, 'over_50'],
]);

it('opens on NOM-035, with nothing declared for Ley Silla yet', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.work-centers.applies', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('norm', 'nom035')->where('people', 0)->where('range', 'up_to_15')->where('seating', null));
});

it('keeps what each center answers in the module that owns each standard', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    answerCenters([['id' => $this->center->id, 'has_teleworkers' => true, 'standing' => true, 'sector' => 'industrial']])
        ->assertRedirect(route('tenant.work-centers.applies', [$this->tenant, $this->center->id]))
        ->assertSessionHasNoErrors();

    expect(TeleworkProfile::query()->sole()->has_teleworkers)->toBeTrue()
        ->and(SeatingProfile::query()->sole()->sector)->toBe(Sector::Industrial)
        ->and(SeatingProfile::query()->sole()->no_standing_positions)->toBeFalse();
    Log::shouldHaveReceived('info')->with('tenancy.applicability_answered', ['work_center_ids' => [$this->center->id]])->once();
});

it('records only the standards it was given an answer for', function (): void {
    $this->actingAs($this->hr);

    answerCenters([['id' => $this->center->id, 'standing' => false]])->assertSessionHasNoErrors();

    expect(TeleworkProfile::query()->exists())->toBeFalse()
        ->and(SeatingProfile::query()->sole()->no_standing_positions)->toBeTrue();
});

it('asks for the sector wherever someone works standing, and for centers that exist in the company', function (array $centers, string $field, string $message): void {
    $this->actingAs($this->hr);

    answerCenters(array_map(fn (array $center): array => ['id' => $this->center->id, ...$center], $centers))->assertSessionHasErrors([$field => $message]);

    expect(SeatingProfile::query()->exists())->toBeFalse();
})->with([
    'no sector' => [[['standing' => true]], 'centers.0.sector', 'El campo sector es obligatorio cuando alguien trabaja de pie sea aceptado.'],
    'unknown sector' => [[['standing' => true, 'sector' => 'mining']], 'centers.0.sector', 'El sector seleccionado no es válido.'],
    'not a yes or no' => [[['standing' => 'maybe']], 'centers.0.standing', 'El campo alguien trabaja de pie debe ser verdadero o falso.'],
    'the same center twice' => [[['standing' => false], ['standing' => false]], 'centers.0.id', 'El campo centro de trabajo tiene un valor duplicado.'],
]);

it('asks for at least one center', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.work-centers.applicability.update', $this->tenant), ['centers' => []])
        ->assertSessionHasErrors(['centers' => 'El campo centros es obligatorio.']);
});

it('answers 404 for an archived center, a made-up one and one of another company', function (string $case): void {
    $center = match ($case) {
        'archived' => WorkCenter::factory()->for($this->tenant)->archived()->create()->id,
        'made-up' => (string) Illuminate\Support\Str::uuid(),
        'another company' => WorkCenter::factory()->create()->id,
    };

    $this->actingAs($this->hr)->get(route('tenant.work-centers.applies', [$this->tenant, $center]))->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.work-centers.applicability.update', $this->tenant), ['centers' => [['id' => $center, 'standing' => false]]])->assertNotFound();

    expect(SeatingProfile::query()->withoutGlobalScopes()->exists())->toBeFalse();
})->with(['archived', 'made-up', 'another company']);

it('lets only whoever manages the standards answer', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.work-centers.applies', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->actingAs($user)->put(route('tenant.work-centers.applicability.update', $this->tenant), ['centers' => [['id' => $this->center->id, 'standing' => false]]])->assertForbidden();
})->with([TenantRole::Viewer]);
