<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Support\DemoListFilters;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DemoListFilters::class);

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

it('schedules the demo of a request on a day in Mexico City time, and logs and audits it', function (): void {
    Log::spy();
    $this->travelTo('2026-12-01 18:00 UTC');
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);

    $this->actingAs(superadmin())->post(route('demo-requests.schedule', $request), ['scheduled_at' => '2026-12-03T11:00'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($request->fresh())
        ->status->toBe(DemoStatus::Scheduled)
        ->scheduled_at->toEqual(Carbon::parse('2026-12-03 17:00 UTC'))
        ->handled_at->toEqual(now());
    Log::shouldHaveReceived('info')->with('demo.scheduled', ['demo_request_id' => $request->id])->once();
    expect(AuditEntry::query()->where('event', 'demo.scheduled')->sole()->subject_id)->toBe($request->id);
});

it('schedules from the day and the hour the dialog sends apart', function (): void {
    $this->travelTo('2026-12-01 18:00 UTC');
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);

    $this->actingAs(superadmin())
        ->post(route('demo-requests.schedule', $request), ['scheduled_on' => '2026-12-03', 'scheduled_time' => '11:00'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($request->fresh()->scheduled_at)->toEqual(Carbon::parse('2026-12-03 17:00 UTC'));
});

it('shows the day of a scheduled demo', function (): void {
    DemoRequest::factory()->create(['status' => DemoStatus::Scheduled, 'scheduled_at' => '2026-12-03 17:00 UTC']);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoRequests.data.0.scheduled_for', 'jue 3 dic, 11:00'));
});

it('asks for a day ahead to schedule a demo', function (array $body): void {
    $this->travelTo('2026-12-01 18:00 UTC');
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->post(route('demo-requests.schedule', $request), $body)->assertSessionHasErrors('scheduled_at');

    expect($request->fresh()->status)->toBe(DemoStatus::New);
})->with([
    'no day' => [[]],
    'not a day' => [['scheduled_at' => 'mañana']],
    'a day gone by' => [['scheduled_at' => '2026-12-01T11:00']],
]);

it('discards a request with its reason, and logs and audits it without the reason', function (): void {
    Log::spy();
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->post(route('demo-requests.discard', $request), ['reason' => 'Buscaba empleo, no una demo'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($request->fresh())->status->toBe(DemoStatus::Discarded)->discard_reason->toBe('Buscaba empleo, no una demo')->handled_at->not->toBeNull();
    Log::shouldHaveReceived('info')->with('demo.discarded', ['demo_request_id' => $request->id])->once();
    $entry = AuditEntry::query()->where('event', 'demo.discarded')->sole();
    expect(json_encode($entry->metadata))->not->toContain('empleo');
});

it('asks why a request is discarded, in up to 500 characters', function (array $body): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->post(route('demo-requests.discard', $request), $body)->assertSessionHasErrors('reason');
})->with([
    'no reason' => [[]],
    'only spaces' => [['reason' => '   ']],
    '501 characters' => [['reason' => str_repeat('a', 501)]],
]);

it('neither schedules nor discards a request that is already a company', function (string $route, array $body, string $field): void {
    $this->travelTo('2026-12-01 18:00 UTC');
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs(superadmin())->post(route($route, $request), $body)->assertSessionHasErrors($field);

    expect($request->fresh()->status)->toBe(DemoStatus::Converted);
})->with([
    'schedule' => ['demo-requests.schedule', ['scheduled_at' => '2026-12-03T11:00'], 'scheduled_at'],
    'discard' => ['demo-requests.discard', ['reason' => 'No'], 'reason'],
]);

it('lets only the superadmin schedule or discard', function (string $route, array $body): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(support())->post(route($route, $request), $body)->assertNotFound();

    expect($request->fresh()->status)->toBe(DemoStatus::New);
})->with([
    'schedule' => ['demo-requests.schedule', ['scheduled_at' => '2030-12-03T11:00']],
    'discard' => ['demo-requests.discard', ['reason' => 'No']],
]);

it('shows why a request was discarded', function (): void {
    DemoRequest::factory()->create(['status' => DemoStatus::Discarded, 'discard_reason' => 'Sin presupuesto']);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoRequests.data.0.discard_reason', 'Sin presupuesto'));
});

it('tells the page whether the user may open the demo room', function (string $role, bool $can): void {
    $this->actingAs($role === 'superadmin' ? superadmin() : support())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('can_open_room', $can));
})->with([
    'the superadmin' => ['superadmin', true],
    'support' => ['support', false],
]);
