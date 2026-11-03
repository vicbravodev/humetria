<?php

declare(strict_types=1);

use App\Modules\Complaints\Actions\OpenChannel;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\AddressKey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AddressKey::class, OpenChannel::class);

const MARKER_IP = '203.0.113.77';
const MARKER_AGENT = 'MarkerBrowser/9.9 (queja-privada)';

/** Whether any text column of any table holds the needle (the database is searched table by table, as a row of text). */
function databaseContains(string $needle): bool
{
    foreach (DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'") as $table) {
        $name = $table->table_name;

        if (DB::selectOne('select count(*) as found from "'.$name.'" t where t::text like ?', ['%'.$needle.'%'])->found > 0) {
            return true;
        }
    }

    return false;
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme Industrial']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['procedure' => 'Así se atiende cada queja.']);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
});

function openChannelPage(object $test, ?string $code = null): Illuminate\Testing\TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => MARKER_IP])
        ->withHeaders(['User-Agent' => MARKER_AGENT, 'Referer' => 'https://empresa.example/portal/token-secreto'])
        ->get('/c/'.($code ?? $test->channel->access_code));
}

it('opens the form of an open channel with what the person needs to read first', function (): void {
    openChannelPage($this)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints-public/form')
            ->where('company', 'Acme Industrial')
            ->where('procedure', 'Así se atiende cada queja.')
            ->where('acknowledgeWithinDays', 3)
            ->where('resolveWithinDays', 30));
});

it('is the same route by name as the address the channel gives out', function (): void {
    expect(route('complaints.public.show', $this->channel->access_code))->toBe($this->channel->publicUrl());
});

it('starts no session, sets no cookie and writes nothing to the sessions table, even for a person signed in', function (): void {
    config(['session.driver' => 'database']);
    $before = DB::table('sessions')->count();

    $response = openChannelPage($this->actingAs($this->owner))->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Set-Cookie'))->toBeFalse()
        ->and(DB::table('sessions')->count())->toBe($before);
    $response->assertInertia(fn (Assert $page) => $page->missing('auth')->missing('tenant')->missing('errors'));
});

it('keeps neither the address, the browser nor the referrer anywhere in the database', function (): void {
    config(['session.driver' => 'database', 'cache.default' => 'database']);

    openChannelPage($this)->assertOk();

    expect(databaseContains(MARKER_IP))->toBeFalse()
        ->and(databaseContains('MarkerBrowser'))->toBeFalse()
        ->and(databaseContains('token-secreto'))->toBeFalse();
});

it('tells browsers and proxies to keep nothing, to send no referrer and not to index it', function (): void {
    $response = openChannelPage($this)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'self' 'nonce-")
        ->and($response->headers->get('Content-Security-Policy'))->not->toContain('https://');
});

it('says the channel is paused and where else to go, without a form', function (): void {
    $this->channel->forceFill(['paused_at' => now()])->save();

    openChannelPage($this)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints-public/unavailable')
            ->where('reason', 'paused')
            ->where('company', 'Acme Industrial')
            ->where('alternative.name', config('complaints.external_alternative.name')));
});

it('answers every other unusable address with the same bare 404', function (string $case): void {
    $code = match ($case) {
        'unknown' => 'ZZZZZZZZZZZZZZZZ',
        'malformed' => 'abc',
        'never activated' => ComplaintChannel::factory()->forTenant(Tenant::factory()->create()->id)->create()->access_code,
        'blocked company' => ComplaintChannel::factory()->forTenant(Tenant::factory()->deactivated()->create()->id)->active()->create()->access_code,
    };

    $response = openChannelPage($this, $code)->assertNotFound();

    $response->assertInertia(fn (Assert $page) => $page->component('complaints-public/unavailable')->where('reason', 'invalid')->where('company', null));
})->with(['unknown', 'malformed', 'never activated', 'blocked company']);

it('takes the code in any case and ignores spaces around it', function (): void {
    openChannelPage($this, strtolower($this->channel->access_code))->assertOk();
});

it('stops a flood from one address after its limit, without affecting another address', function (): void {
    config(['complaints.rate_limits.views_per_hour' => 3]);
    // The limiter was registered at boot with the configured number: register again with this one.
    Illuminate\Support\Facades\RateLimiter::for('complaints-view', fn (Request $request) => Illuminate\Cache\RateLimiting\Limit::perHour(3)->by(AddressKey::for($request)));

    foreach (range(1, 3) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/c/'.$this->channel->access_code)->assertOk();
    }

    $stopped = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/c/'.$this->channel->access_code)->assertStatus(429);
    // It speaks «usted», as the survey does, and still leaves no cookie behind.
    $stopped->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 429)->where('survey', true));
    expect($stopped->headers->getCookies())->toBe([]);
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])->get('/c/'.$this->channel->access_code)->assertOk();
});

it('keys the limits by a keyed hash of the address, never the address', function (): void {
    $request = Request::create('/c/x', 'GET', server: ['REMOTE_ADDR' => MARKER_IP]);
    $key = AddressKey::for($request);

    expect($key)->not->toContain(MARKER_IP)
        ->and($key)->toBe(AddressKey::for($request))
        ->and($key)->not->toBe(AddressKey::for(Request::create('/c/x', 'GET', server: ['REMOTE_ADDR' => '203.0.113.78'])))
        ->and($key)->toStartWith('complaints:')
        ->and($key)->toBe('complaints:'.hash_hmac('sha256', MARKER_IP, config()->string('app.key')));
});

it('gives the form what it needs to ask, and which options nobody independent would receive', function (): void {
    $this->travelTo('2026-11-20 18:00:00');

    openChannelPage($this)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('code', $this->channel->access_code)
            ->where('today', '2026-11-20')
            ->where('descriptionLimits', ['min' => 20, 'max' => 5000])
            ->has('types', 3)
            ->where('types.0.value', 'unfavorable_practice')
            ->where('types.2.value', 'traumatic_event_notice')
            ->has('kinds', 6)
            ->where('kinds.2', ['value' => 'hr', 'label' => 'Recursos humanos', 'available' => true])
            ->where('kinds.3', ['value' => 'management', 'label' => 'La dirección o el dueño', 'available' => false])
            ->has('categories', 10)
            ->where('categories.0', ['value' => 'harassment', 'label' => 'Hostigamiento', 'type' => 'workplace_violence'])
            ->has('ticket'));
});

it('gives a ticket the submission accepts, and none that names the person', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $ticket = openChannelPage($this)->viewData('page')['props']['ticket'] ?? null;

    $this->travel(5)->seconds();

    expect($ticket)->toBeString()
        ->and(resolve(App\Modules\Complaints\Support\TicketSigner::class)->isValid($ticket, $this->channel->access_code))->toBeTrue()
        ->and($ticket)->not->toContain(MARKER_IP);
});

it('does not tell the page who handles the complaints, or how many', function (): void {
    $response = openChannelPage($this)->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain($this->owner->email)->not->toContain($this->owner->name);
});
