<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\RetireWorkplace;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RetireWorkplace::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id]);
    $this->place = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->person->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function retireUrl(Workplace $place): string
{
    return route('tenant.telework.workplaces.retire', [test()->tenant, $place->id]);
}

it('retires a workplace without deleting it and logs only ids', function (): void {
    Log::spy();

    $this->actingAs($this->hr)->post(retireUrl($this->place))->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $this->person->id]));

    expect(Workplace::query()->findOrFail($this->place->id)->retired_at?->toDateTimeString())->toBe('2026-03-01 12:00:00');
    Log::shouldHaveReceived('info')->with('telework.workplace_retired', ['teleworker_id' => $this->person->id, 'workplace_id' => $this->place->id])->once();
});

it('keeps the first retirement date when asked twice', function (): void {
    $this->actingAs($this->hr)->post(retireUrl($this->place));
    $this->travelTo('2026-03-05 12:00:00');
    $this->actingAs($this->hr)->post(retireUrl($this->place))->assertRedirect();

    expect(Workplace::query()->findOrFail($this->place->id)->retired_at?->toDateString())->toBe('2026-03-01');
});

it('is only for whoever manages NOM-037, and a place of another company is a 404', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(retireUrl($this->place))->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->post(retireUrl($this->place))->assertForbidden();

    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $this->actingAs(memberOf($rival, TenantRole::Hr))->post(route('tenant.telework.workplaces.retire', [$rival, $this->place->id]))->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.telework.workplaces.retire', [$this->tenant, 'nope']))->assertNotFound();

    expect(Workplace::query()->findOrFail($this->place->id)->retired_at)->toBeNull();
});

it('shows the retired state on the person page', function (): void {
    $this->actingAs($this->hr)->post(retireUrl($this->place));

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->person->id]))->assertInertia(fn (Assert $page) => $page
        ->where('teleworker.workplaces.0.status', 'retired'));
});
