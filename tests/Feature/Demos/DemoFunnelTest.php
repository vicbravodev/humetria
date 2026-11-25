<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

it('names the stages of the funnel', function (DemoStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [DemoStatus::New, 'Nueva'],
    [DemoStatus::Contacted, 'Contactada'],
    [DemoStatus::Scheduled, 'Demo agendada'],
    [DemoStatus::Trial, 'Prueba activa'],
    [DemoStatus::Converted, 'Convertida'],
    [DemoStatus::Discarded, 'Descartada'],
]);

it('lets the database keep a scheduled demo and an active trial', function (string $status): void {
    $request = DemoRequest::factory()->create();

    DB::table('demo_requests')->where('id', $request->id)->update(['status' => $status]);

    expect($request->fresh()->status->value)->toBe($status);
})->with(['scheduled', 'trial']);

it('moves a request to an active trial by hand', function (): void {
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'trial'])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(DemoStatus::Trial);
});

it('refuses to schedule a demo by hand: scheduling asks for its day', function (): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'scheduled'])->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(DemoStatus::New);
});

it('keeps a scheduled request scheduled when only its note changes', function (): void {
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Scheduled]);

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'scheduled', 'note' => 'Confirmó'])->assertSessionHasNoErrors();

    expect($request->fresh())->status->toBe(DemoStatus::Scheduled)->note->toBe('Confirmó');
});

it('gives a request someone of the staff to own it and its next step, and logs who without the step', function (): void {
    Log::spy();
    $owner = support();
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), [
        'status' => 'contacted', 'owner_user_id' => $owner->id, 'next_step' => 'Mandar la propuesta el lunes',
    ])->assertSessionHasNoErrors();

    expect($request->fresh())->owner_user_id->toBe($owner->id)->next_step->toBe('Mandar la propuesta el lunes');
    Log::shouldHaveReceived('info')->with('demo.assigned', ['demo_request_id' => $request->id, 'owner_user_id' => $owner->id])->once();
    $entry = AuditEntry::query()->where('event', 'demo.assigned')->sole();
    expect($entry->subject_id)->toBe($request->id)->and(json_encode($entry->metadata))->not->toContain('propuesta');
});

it('does not log an assignment when the owner stays the same', function (): void {
    $owner = support();
    $request = DemoRequest::factory()->create(['owner_user_id' => $owner->id]);

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'contacted', 'owner_user_id' => $owner->id]);

    expect(AuditEntry::query()->where('event', 'demo.assigned')->count())->toBe(0);
});

it('takes the owner away when none is sent', function (): void {
    $request = DemoRequest::factory()->create(['owner_user_id' => support()->id, 'next_step' => 'Llamar']);

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'new'])->assertSessionHasNoErrors();

    expect($request->fresh())->owner_user_id->toBeNull()->next_step->toBeNull();
});

it('only lets someone of the staff own a request', function (int|string $someone): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'new', 'owner_user_id' => $someone])
        ->assertSessionHasErrors('owner_user_id');
})->with([
    'a company owner' => fn () => ownerOf(Tenant::factory()->create())->id,
    'someone with no role' => fn () => User::factory()->create()->id,
    'nobody' => fn () => 999999,
    'not a number' => fn () => 'ana',
]);

it('takes a next step of up to 200 characters', function (): void {
    $request = DemoRequest::factory()->create();
    $this->actingAs(superadmin());

    $this->put(route('demo-requests.update', $request), ['status' => 'new', 'next_step' => str_repeat('a', 200)])->assertSessionHasNoErrors();
    $this->put(route('demo-requests.update', $request), ['status' => 'new', 'next_step' => str_repeat('a', 201)])->assertSessionHasErrors('next_step');
});

it('clears the owner of a request when that person leaves', function (): void {
    $owner = support();
    $request = DemoRequest::factory()->create(['owner_user_id' => $owner->id]);

    $owner->delete();

    expect($request->fresh()->owner_user_id)->toBeNull();
});

it('shows each request with its owner and next step', function (): void {
    $owner = support();
    $owner->update(['name' => 'Lucía Vega']);
    DemoRequest::factory()->create(['owner_user_id' => $owner->id, 'next_step' => 'Llamar el jueves']);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.owner', ['id' => $owner->id, 'name' => 'Lucía Vega'])
            ->where('demoRequests.data.0.next_step', 'Llamar el jueves'));
});

it('offers the staff, and nobody else, to own a request, once the page asks for them', function (): void {
    $boss = superadmin();
    $boss->update(['name' => 'Beto']);
    $help = support();
    $help->update(['name' => 'Ana']);
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner);

    $this->actingAs($boss)->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('staff')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('staff', [['id' => $help->id, 'name' => 'Ana'], ['id' => $boss->id, 'name' => 'Beto']])));
});

