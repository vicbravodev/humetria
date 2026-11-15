<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Demos\Actions\UpdateDemoRequest;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Notifications\DemoRequested;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(UpdateDemoRequest::class);

/** The landing's form, as a visitor fills it. */
function landingDemoForm(array $overrides = []): array
{
    return [
        'name' => 'Ana López', 'email' => 'ana@empresa.mx', 'whatsapp' => '55 1234 5678', 'company' => 'Empresa Ejemplo',
        'profile' => 'company', 'workforce' => '51_100', 'tools' => ['nom_035'], 'message' => 'Hola', 'form_extra' => '',
        ...$overrides,
    ];
}

it('starts every request as new', function (): void {
    $this->post(route('demo-requests.store'), landingDemoForm());

    $request = DemoRequest::query()->sole();
    expect($request->status)->toBe(DemoStatus::New)
        ->and($request->note)->toBeNull()
        ->and($request->handled_at)->toBeNull()
        ->and($request->tenant_id)->toBeNull();
});

it('tells every superadmin, and not support, when a request arrives', function (): void {
    Notification::fake();
    $one = superadmin();
    $two = superadmin();
    $help = support();

    $this->post(route('demo-requests.store'), landingDemoForm());

    Notification::assertSentTo([$one, $two], DemoRequested::class);
    Notification::assertNotSentTo($help, DemoRequested::class);
});

it('takes a request even when there is no superadmin to tell', function (): void {
    Notification::fake();

    $this->post(route('demo-requests.store'), landingDemoForm())->assertSessionHasNoErrors();

    expect(DemoRequest::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('does not tell anyone about a bot', function (): void {
    Notification::fake();
    superadmin();

    $this->post(route('demo-requests.store'), landingDemoForm(['form_extra' => 'spam']));

    Notification::assertNothingSent();
});

it('rejects the update to anyone but the superadmin', function (User $user): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs($user)->put(route('demo-requests.update', $request), ['status' => 'contacted', 'note' => 'x'])->assertNotFound();

    expect($request->fresh()->status)->toBe(DemoStatus::New);
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('marks a request as contacted with a note, stamps the day and logs it without the note', function (): void {
    Log::spy();
    $this->travelTo('2026-11-12 18:00 UTC');
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'contacted', 'note' => 'Llamé, quedó de contestar'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($request->fresh())
        ->status->toBe(DemoStatus::Contacted)
        ->note->toBe('Llamé, quedó de contestar')
        ->handled_at->toEqual(now());
    Log::shouldHaveReceived('info')->once()->with('demo.updated', ['demo_request_id' => $request->id, 'status' => 'contacted']);
    $entry = AuditEntry::query()->where('event', 'demo.updated')->sole();
    expect($entry->subject_id)->toBe($request->id)->and(json_encode($entry->metadata))->not->toContain('Llamé');
});

it('keeps the first day it was handled when it changes again, and clears it when it goes back to new', function (): void {
    $request = DemoRequest::factory()->create();
    $this->actingAs(superadmin());
    $this->travelTo('2026-11-12 18:00 UTC');
    $this->put(route('demo-requests.update', $request), ['status' => 'contacted', 'note' => null]);
    $this->travelTo('2026-11-14 18:00 UTC');
    $this->put(route('demo-requests.update', $request), ['status' => 'discarded', 'note' => null]);

    expect($request->fresh()->handled_at->toDateString())->toBe('2026-11-12');

    $this->put(route('demo-requests.update', $request), ['status' => 'new', 'note' => null]);

    expect($request->fresh()->handled_at)->toBeNull();
});

it('refuses to mark a request converted by hand: only creating the company does it', function (): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'converted', 'note' => null])
        ->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(DemoStatus::New);
});

it('validates the note and the status', function (array $body, string $field): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), $body)->assertSessionHasErrors($field);
})->with([
    'a note of 2001 characters' => [['status' => 'new', 'note' => str_repeat('a', 2001)], 'note'],
    'no status' => [['note' => 'x'], 'status'],
    'a status that does not exist' => [['status' => 'won', 'note' => null], 'status'],
]);

it('accepts a note of 2000 characters', function (): void {
    $request = DemoRequest::factory()->create();

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'new', 'note' => str_repeat('a', 2000)])->assertSessionHasNoErrors();
});

it('only changes the note of a request that was already converted', function (): void {
    $tenant = Tenant::factory()->create();
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'tenant_id' => $tenant->id]);

    $this->actingAs(superadmin())->put(route('demo-requests.update', $request), ['status' => 'discarded', 'note' => 'Ya es cliente'])->assertSessionHasNoErrors();

    expect($request->fresh())->status->toBe(DemoStatus::Converted)->note->toBe('Ya es cliente')->tenant_id->toBe($tenant->id);
});

it('lists a request with its status, its note and the company it became', function (): void {
    $tenant = Tenant::factory()->create();
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'note' => 'Cliente', 'tenant_id' => $tenant->id]);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.id', $request->id)
            ->where('demoRequests.data.0.status', 'converted')
            ->where('demoRequests.data.0.status_label', 'Convertida')
            ->where('demoRequests.data.0.note', 'Cliente')
            ->where('demoRequests.data.0.tenant_id', $tenant->id));
});

it('counts the requests of each status for the tabs, and filters by one', function (): void {
    DemoRequest::factory()->count(2)->create();
    DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);
    DemoRequest::factory()->count(3)->create(['status' => DemoStatus::Discarded]);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['all' => 6, 'new' => 2, 'contacted' => 1, 'converted' => 0, 'discarded' => 3])
            ->where('filters.status', 'all')
            ->where('demoRequests.total', 6));

    $this->get(route('demo-requests.index', ['status' => 'discarded']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'discarded')
            ->where('demoRequests.total', 3)
            ->where('counts.all', 6));
});

it('ignores a status that does not exist in the filter', function (): void {
    DemoRequest::factory()->count(2)->create();

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['status' => 'won']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'all')->where('demoRequests.total', 2));
});

it('paginates the filtered tab and keeps the filter in its links', function (): void {
    DemoRequest::factory()->count(26)->create(['status' => DemoStatus::Contacted]);

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['status' => 'contacted']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('demoRequests.data', 25)
            ->where('demoRequests.next_page_url', route('demo-requests.index', ['status' => 'contacted', 'page' => 2])));
});

it('offers the contract of other modules a request by id, and not one that does not exist', function (): void {
    $request = DemoRequest::factory()->create(['company' => 'Empresa Ejemplo', 'name' => 'Ana López', 'email' => 'ana@empresa.mx']);
    $leads = app(DemoLeads::class);

    $lead = $leads->find($request->id);

    expect($lead)->not->toBeNull()
        ->and($lead->company)->toBe('Empresa Ejemplo')
        ->and($lead->contactName)->toBe('Ana López')
        ->and($lead->email)->toBe('ana@empresa.mx')
        ->and($lead->tenantId)->toBeNull()
        ->and($leads->find('no-es-un-uuid'))->toBeNull()
        ->and($leads->find((string) Illuminate\Support\Str::uuid()))->toBeNull();
});

it('marks a request converted once: the second try changes nothing and says so', function (): void {
    Log::spy();
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Contacted]);
    $leads = app(DemoLeads::class);

    expect($leads->markConverted($request->id, $first->id))->toBeTrue()
        ->and($leads->markConverted($request->id, $second->id))->toBeFalse();

    expect($request->fresh())->status->toBe(DemoStatus::Converted)->tenant_id->toBe($first->id)->handled_at->not->toBeNull();
    Log::shouldHaveReceived('info')->once()->with('demo.converted', ['demo_request_id' => $request->id, 'tenant_id' => $first->id]);
    expect(AuditEntry::query()->where('event', 'demo.converted')->count())->toBe(1);
});

it('keeps the company when its request goes away: the link is just cleared', function (): void {
    $tenant = Tenant::factory()->create();
    $request = DemoRequest::factory()->create(['status' => DemoStatus::Converted, 'tenant_id' => $tenant->id]);

    $tenant->delete();

    expect($request->fresh()->tenant_id)->toBeNull();
});

it('lets the database refuse a status that is not one of the four', function (): void {
    $request = DemoRequest::factory()->create();

    DB::table('demo_requests')->where('id', $request->id)->update(['status' => 'won']);
})->throws(Illuminate\Database\QueryException::class);
