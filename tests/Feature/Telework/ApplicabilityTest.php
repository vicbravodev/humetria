<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\DeclareApplicability;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Support\CompanyTeleworkApplicability;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DeclareApplicability::class, CompanyTeleworkApplicability::class);

// Rediseño v2 (board V2-Aplica, decisión A2): before its route, each center answers whether someone works away from it more
// than 40 % of their time (LFT 330-A), and the answer is kept with who gave it and when.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function declareTelework(bool $teleworks, ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->put(route('tenant.telework.applies.update', [test()->tenant, ($center ?? test()->center)->id]), ['has_teleworkers' => $teleworks]);
}

it('asks a center whether NOM-037 applies there, with what it answered before', function (): void {
    $this->actingAs($this->hr)
        ->get(route('tenant.telework.applies.show', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->component('telework/applies')
            ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Querétaro'])
            ->where('declaration', null));

    declareTelework(false);

    $this->get(route('tenant.telework.applies.show', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('declaration', ['hasTeleworkers' => false, 'declaredOn' => '1 mar 2026']));
});

it('records the answer with who and when, audits it and logs it with ids only', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    declareTelework(true)->assertRedirect(route('tenant.telework.applies.show', [$this->tenant, $this->center->id]))->assertSessionHasNoErrors();

    $profile = TeleworkProfile::query()->sole();
    $record = ['work_center_id' => $this->center->id, 'has_teleworkers' => true];

    expect($profile->work_center_id)->toBe($this->center->id)
        ->and($profile->has_teleworkers)->toBeTrue()
        ->and($profile->declared_by)->toBe($this->hr->id)
        ->and($profile->declared_on->toDateString())->toBe('2026-03-01')
        ->and(AuditEntry::query()->where('event', 'telework.applicability_declared')->sole()->metadata)->toEqual($record);
    Log::shouldHaveReceived('info')->with('telework.applicability_declared', $record)->once();
});

it('keeps one answer per center: answering again replaces it, with the new date and author', function (): void {
    $this->actingAs($this->hr);
    declareTelework(true);
    $this->travelTo('2026-04-10 12:00:00');
    $owner = memberOf($this->tenant, TenantRole::Owner);

    $this->actingAs($owner);
    declareTelework(false)->assertSessionHasNoErrors();

    $profile = TeleworkProfile::query()->sole();

    expect($profile->has_teleworkers)->toBeFalse()
        ->and($profile->declared_by)->toBe($owner->id)
        ->and($profile->declared_on->toDateString())->toBe('2026-04-10');
});

it('asks for a yes or a no', function (mixed $answer): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.telework.applies.update', [$this->tenant, $this->center->id]), $answer === 'missing' ? [] : ['has_teleworkers' => $answer])
        ->assertSessionHasErrors(['has_teleworkers' => $answer === 'missing' ? 'El campo alguien teletrabaja es obligatorio.' : 'El campo alguien teletrabaja debe ser verdadero o falso.']);

    expect(TeleworkProfile::query()->exists())->toBeFalse();
})->with(['missing', 'maybe']);

it('lets only whoever manages NOM-037 answer', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.telework.applies.show', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->actingAs($user)->put(route('tenant.telework.applies.update', [$this->tenant, $this->center->id]), ['has_teleworkers' => true])->assertForbidden();

    expect(TeleworkProfile::query()->exists())->toBeFalse();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('answers 404 for an archived center, a made-up one and one of another company', function (string $case): void {
    $center = match ($case) {
        'archived' => WorkCenter::factory()->for($this->tenant)->archived()->create()->id,
        'made-up' => (string) Illuminate\Support\Str::uuid(),
        'not a uuid' => 'nope',
        'another company' => WorkCenter::factory()->create()->id,
    };

    $this->actingAs($this->hr)->get(route('tenant.telework.applies.show', [$this->tenant, $center]))->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.telework.applies.update', [$this->tenant, $center]), ['has_teleworkers' => true])->assertNotFound();
})->with(['archived', 'made-up', 'not a uuid', 'another company']);

it('keeps one answer per center in the database too', function (): void {
    $profile = TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    expect(fn () => DB::transaction(fn () => TeleworkProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $profile->work_center_id])))
        ->toThrow(QueryException::class, 'telework_profiles_work_center_unique');
});

it('answers for other modules what each center declared, and records a new answer the same way', function (): void {
    $offices = WorkCenter::factory()->for($this->tenant)->create();
    WorkCenter::factory()->for($this->tenant)->create();
    TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id]);
    $theirs = TeleworkProfile::factory()->create();
    $applicability = app(TeleworkApplicability::class);

    $applicability->declare($this->center->id, false, $this->hr->id);

    expect($applicability->declarations([$this->center->id, $offices->id, $theirs->work_center_id]))->toEqual([$this->center->id => false, $offices->id => true])
        ->and(AuditEntry::query()->where('event', 'telework.applicability_declared')->count())->toBe(1);
});
