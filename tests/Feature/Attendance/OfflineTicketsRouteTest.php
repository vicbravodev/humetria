<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * `POST me/{slug}/tickets`: a phone with a valid ticket of the person gets a new batch and what it needs to mark without a
 * connection. No session, no cookies and no forgery check: after a blackout there may be none, and a ticket is inert
 * without the signature of a mark.
 */
beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost']);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->ticket = resolve(OfflineTicket::class)->batch($this->ana->id)[0]['ticket'];
});

/** @param  array<string, mixed>  $body */
function ticketsFor(?string $ticket, array $body = [], string $slug = 'acme'): TestResponse
{
    return test()->postJson(route('attendance.device.tickets', $slug), ['ticket' => $ticket, ...$body]);
}

it('swaps a valid ticket for a new batch and the context, without a session, a cookie or a row of sessions', function (): void {
    config(['session.driver' => 'database']);

    $response = ticketsFor($this->ticket)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Set-Cookie');

    expect($response->json('tickets'))->toHaveCount(8)
        ->and($response->json('tickets.0'))->toHaveKeys(['ticket', 'issuedAt'])
        ->and($response->json('context'))->toBe(['tenantId' => $this->tenant->id, 'employeeId' => $this->ana->id, 'centerId' => $this->plant->id, 'agreementVersion' => null, 'rpId' => 'localhost', 'offline' => true])
        ->and(DB::table('sessions')->count())->toBe(0);
});

it('tells the version of the agreement the person accepted', function (): void {
    agreementAccepted($this->ana->id);

    expect(ticketsFor($this->ticket)->assertOk()->json('context.agreementVersion'))->toBe(1);
});

it('serves a ticket of 35 days and no more', function (): void {
    $this->travel(35)->days();
    ticketsFor($this->ticket)->assertOk();

    $this->travel(1)->seconds();
    ticketsFor($this->ticket)->assertNotFound();
});

it('answers the same 404 to a ticket that was altered, of another company or of someone who left, and to a company without the registro de jornada', function (string $cause): void {
    $slug = 'acme';
    $ticket = $this->ticket;

    match ($cause) {
        'altered' => $ticket = substr($ticket, 0, -1).($ticket[-1] === 'A' ? 'B' : 'A'),
        'another company' => $slug = Tenant::factory()->create(['slug' => 'other'])->slug,
        'someone who left' => $this->ana->update(['deactivated_at' => now()]),
        'a person that does not exist' => $ticket = resolve(OfflineTicket::class)->batch('9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c')[0]['ticket'],
        'no module' => resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone'))),
    };

    ticketsFor($ticket, slug: $slug)->assertNotFound()->assertJsonMissingPath('tickets');
})->with(['altered', 'another company', 'someone who left', 'a person that does not exist', 'no module']);

it('answers a JSON 404 to a company that does not exist, with no Accept header', function (): void {
    $this->post(route('attendance.device.tickets', 'nowhere'), ['ticket' => $this->ticket])->assertNotFound()->assertHeader('Content-Type', 'application/json');
});

it('does not take a ticket that is missing, too long or that has a bar', function (mixed $ticket): void {
    ticketsFor($ticket)->assertUnprocessable()->assertJsonValidationErrors('ticket')->assertJsonMissingPath('tickets');
})->with([[null], [str_repeat('a', 257)], ['v1.a|b.c']]);

it('records only the size and the age of the queue the phone reports, and no person', function (): void {
    Log::spy();

    ticketsFor($this->ticket, ['queue' => ['count' => 3, 'oldest_age_minutes' => 90]])->assertOk();

    Log::shouldHaveReceived('info')->once()->with('attendance.offline_queue_reported', ['count' => 3, 'oldest_age_minutes' => 90]);
});

it('does not log a queue that was not reported, and rejects a queue of the wrong shape', function (): void {
    Log::spy();

    ticketsFor($this->ticket)->assertOk();
    ticketsFor($this->ticket, ['queue' => ['count' => -1, 'oldest_age_minutes' => 'a lot']])->assertUnprocessable()->assertJsonValidationErrors(['queue.count', 'queue.oldest_age_minutes']);

    Log::shouldNotHaveReceived('info', ['attendance.offline_queue_reported', Mockery::any()]);
});

it('refuses a request that comes from another site', function (): void {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->postJson(route('attendance.device.tickets', 'acme'), ['ticket' => $this->ticket])->assertForbidden();
    $this->withHeader('Sec-Fetch-Site', 'same-origin')->postJson(route('attendance.device.tickets', 'acme'), ['ticket' => $this->ticket])->assertOk();
});

it('takes no session nor forgery check, and refuses another site', function (): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName('attendance.device.tickets') ?? throw new LogicException('The route is missing.'));

    expect($middleware)->not->toContain(Illuminate\Session\Middleware\StartSession::class)
        ->not->toContain(Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->not->toContain(Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class)
        ->not->toContain(Illuminate\View\Middleware\ShareErrorsFromSession::class)
        ->not->toContain(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
        ->toContain(App\Http\Middleware\RejectCrossSiteRequests::class);
});

it('limits the address to 300 requests a minute, apart from the screen of the center', function (): void {
    $request = Request::create(route('attendance.device.tickets', 'acme'), 'POST');
    $limit = RateLimiter::limiter('attendance-tickets')?->__invoke($request);

    expect($limit)->maxAttempts->toBe(300)->decaySeconds->toBe(60)->key->toBe('attendance-tickets:'.$request->ip());

    foreach (range(1, 300) as $ignored) {
        ticketsFor(null);
    }

    ticketsFor($this->ticket)->assertTooManyRequests();
});
