<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Models\AuditEntry;
use App\Modules\Tenancy\Http\Requests\ImpersonateRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ImpersonateRequest::class);

it('asks the reason and how long before entering a real company', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant))
        ->assertSessionHasErrors(['reason', 'minutes' => 'Elige cuánto tiempo vas a estar dentro.']);

    expect(session(Impersonation::SESSION_KEY))->toBeNull();
});

it('takes a reason of three to five hundred characters and thirty, sixty or one hundred twenty minutes', function (array $entry, ?string $error): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $response = $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant), $entry);

    $error === null ? $response->assertRedirect(route('dashboard')) : $response->assertSessionHasErrors($error);
})->with([
    'two characters' => [['reason' => 'ab', 'minutes' => 30], 'reason'],
    'three characters' => [['reason' => 'abc', 'minutes' => 30], null],
    'five hundred' => [['reason' => str_repeat('a', 500), 'minutes' => 30], null],
    'five hundred one' => [['reason' => str_repeat('a', 501), 'minutes' => 30], 'reason'],
    'sixty minutes' => [['reason' => 'Invitación rebotada', 'minutes' => 60], null],
    'two hours' => [['reason' => 'Invitación rebotada', 'minutes' => 120], null],
    'forty-five minutes' => [['reason' => 'Invitación rebotada', 'minutes' => 45], 'minutes'],
]);

it('leaves the reason and the minutes in the audit trail', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant), ['reason' => 'Invitación rebotada', 'minutes' => 60]);

    expect(AuditEntry::query()->where('event', 'impersonation.started')->sole()->metadata)
        ->toBe(['reason' => 'Invitación rebotada', 'minutes' => 60, 'tenant_id' => $tenant->id]);
});

it('tells the banner how many seconds are left', function (): void {
    $this->travelTo('2026-11-25 18:00 UTC');
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant), supportEntry());

    $this->travel(10)->minutes();

    $this->followingRedirects()->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('impersonationLeft', 20 * 60));
});

it('ends the entry when its time is up: back to the console as the staff, with the end in the audit trail', function (): void {
    $this->travelTo('2026-11-25 18:00 UTC');
    $superadmin = superadmin();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant), supportEntry());
    Log::spy();

    $this->travel(29)->minutes();
    $this->followingRedirects()->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($owner);

    $this->travel(61)->seconds();
    $this->get(route('dashboard'))->assertRedirect(route('companies.index'));

    $this->assertAuthenticatedAs($superadmin);
    expect(session(Impersonation::SESSION_KEY))->toBeNull()
        ->and(AuditEntry::query()->where('event', 'impersonation.expired')->sole())
        ->actor_id->toBe($owner->id)
        ->impersonator_id->toBe($superadmin->id);
    Log::shouldHaveReceived('info')->with('impersonation.expired', Mockery::any())->once();
});

it('never opens the personal account of whoever it works as', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $tenant), supportEntry());

    $this->get(route('profile.edit'))->assertNotFound();
});
