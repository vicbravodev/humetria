<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Models\User;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;

mutates(Analytics::class);

const PH_TOKEN = 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG';
const PH_HOST = 'https://us.i.posthog.com';

/**
 * A request the router has already matched, as the middleware and the views see it.
 *
 * @param  array<string, string>  $server
 */
function matchedRequest(string $uri = '/', ?User $user = null, array $server = []): Request
{
    $request = Request::create($uri, server: $server);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

function measuring(string $token = PH_TOKEN, string $consent = 'granted'): void
{
    config(['services.posthog.project_token' => $token, 'services.posthog.consent_default' => $consent]);
}

it('measures the landing for a visitor without a session when there is a token and consent', function (): void {
    measuring();

    expect(Analytics::projectToken(matchedRequest()))->toBe(PH_TOKEN);
});

it('measures nothing without a token, without consent, outside the landing or for someone signed in', function (Closure $setUp, string $uri, bool $signedIn): void {
    measuring();
    $setUp();

    expect(Analytics::projectToken(matchedRequest($uri, $signedIn ? User::factory()->create() : null)))->toBeNull();
})->with([
    'no token' => [fn () => measuring(''), '/', false],
    'consent denied' => [fn () => measuring(consent: 'denied'), '/', false],
    'another page' => [fn () => null, '/privacy-notice', false],
    'the login' => [fn () => null, '/admin/login', false],
    'a session' => [fn () => null, '/', true],
]);

it('accepts only a well-formed PostHog project token', function (string $token, bool $valid): void {
    measuring($token);

    expect(Analytics::projectToken(matchedRequest()))->toBe($valid ? $token : null);
})->with([
    'a real-looking token' => [PH_TOKEN, true],
    'twenty characters' => ['phc_'.str_repeat('a', 20), true],
    'nineteen characters' => ['phc_'.str_repeat('a', 19), false],
    'a hundred characters' => ['phc_'.str_repeat('a', 100), true],
    'a hundred and one' => ['phc_'.str_repeat('a', 101), false],
    'a personal api key' => ['phx_'.str_repeat('a', 43), false],
    'no prefix' => [str_repeat('a', 43), false],
    'a space around' => [' '.PH_TOKEN, false],
    'a trailing newline' => [PH_TOKEN."\n", false],
    'markup' => ['phc_'.str_repeat('a', 20).'"></script><script>x', false],
    'a query' => ['phc_'.str_repeat('a', 20).'&x=1', false],
    'google analytics id' => ['G-ABC123XYZ9', false],
]);

it('gives PostHog only the origin of a referrer from this very site, and nothing it can read by itself', function (?string $referer, ?string $expected): void {
    config(['app.url' => 'https://humetria.com.mx']);
    $server = $referer === null ? [] : ['HTTP_REFERER' => $referer];

    expect(Analytics::referrer(matchedRequest('/', server: $server + ['HTTP_HOST' => 'humetria.com.mx'])))->toBe($expected);
})->with([
    'none' => [null, null],
    'empty' => ['', null],
    'external' => ['https://www.google.com/search?q=humetria', null],
    'own page with a token' => ['https://humetria.com.mx/encuesta/abc123token?x=1#y', 'https://humetria.com.mx/'],
    'own www variant' => ['https://www.humetria.com.mx/admin/secret', 'https://www.humetria.com.mx/'],
    'own host in capitals' => ['https://HUMETRIA.com.mx/reporte/token', 'https://humetria.com.mx/'],
    'own host with a port' => ['https://humetria.com.mx:8443/x', 'https://humetria.com.mx:8443/'],
    'another subdomain' => ['https://app.humetria.com.mx/x', null],
    'a lookalike host' => ['https://otrohumetria.com.mx/x', null],
    'a host that ends in ours' => ['https://humetria.com.mx.evil.example/x', null],
    'not a url' => ['humetria', null],
    'a script scheme' => ['javascript:alert(1)', null],
]);

it('tells PostHog the page without its personal query: only the utm parameters stay', function (string $uri, string $expected): void {
    expect(Analytics::pageLocation(matchedRequest($uri)))->toBe($expected);
})->with([
    'plain' => ['/', 'http://localhost/'],
    'a token that is not a campaign' => ['/?token=secret&email=a@b.mx', 'http://localhost/'],
    'campaign parameters' => ['/?utm_source=google&utm_medium=cpc&utm_campaign=nom-035&fbclid=x', 'http://localhost/?utm_source=google&utm_medium=cpc&utm_campaign=nom-035'],
    'an array value' => ['/?utm_source[]=a', 'http://localhost/'],
    'an empty value' => ['/?utm_source=', 'http://localhost/'],
    'a value of 101 characters' => ['/?utm_source='.str_repeat('a', 101), 'http://localhost/'],
    'a value of 100 characters' => ['/?utm_source='.str_repeat('a', 100), 'http://localhost/?utm_source='.str_repeat('a', 100)],
    'the click id of an ad' => ['/?gclid=abc&utm_term=empresa', 'http://localhost/?utm_term=empresa'],
]);

function landingHtml(string $uri = '/', ?string $referer = null): string
{
    return (string) test()->withHeaders($referer === null ? [] : ['Referer' => $referer])->get($uri)->assertOk()->getContent();
}

/** What the landing hands the browser: the JSON block of the analytics configuration, decoded. */
function analyticsConfigIn(string $html): ?array
{
    if (preg_match('/<script type="application\/json" id="analytics-config">(.*?)<\/script>/s', $html, $match) !== 1) {
        return null;
    }

    return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
}

it('hands the browser the token, the host and the page as a JSON block, and runs no script of its own', function (): void {
    measuring();

    $html = landingHtml();

    expect(analyticsConfigIn($html))->toBe(['token' => PH_TOKEN, 'host' => PH_HOST, 'page_location' => 'http://localhost/'])
        ->and($html)->not->toContain('googletagmanager')
        ->and($html)->not->toContain('dataLayer')
        ->and($html)->not->toContain('us-assets.i.posthog.com');
});

it('tells PostHog the page without its personal query and no referrer unless it is a page of this site', function (): void {
    measuring();

    $html = landingHtml('/?token=SECRET-TOKEN&utm_source=google&email=a@b.mx', 'https://www.google.com/search?q=SECRET-SEARCH');
    $config = analyticsConfigIn($html);

    expect($config)->toBe(['token' => PH_TOKEN, 'host' => PH_HOST, 'page_location' => 'http://localhost/?utm_source=google'])
        ->and(json_encode($config))->not->toContain('SECRET');
});

it('sends a referrer from this site as its origin only: a link with a token never reaches PostHog', function (): void {
    measuring();

    $config = analyticsConfigIn(landingHtml('/', 'http://localhost/encuesta/TOKEN-OF-A-SURVEY?x=1'));

    expect($config['page_referrer'] ?? null)->toBe('http://localhost/')
        ->and(json_encode($config))->not->toContain('TOKEN-OF-A-SURVEY');
});

it('writes the block safely: a token or a campaign value cannot close the script tag', function (): void {
    measuring();

    $html = landingHtml('/?utm_source=</script><script>alert(1)</script>');

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and(analyticsConfigIn($html)['page_location'] ?? null)->toBe('http://localhost/?utm_source=%3C%2Fscript%3E%3Cscript%3Ealert%281%29%3C%2Fscript%3E');
});

it('has no measurement block unless the four conditions hold, and PostHog only for errors elsewhere', function (Closure $setUp, string $uri, bool $signedIn, bool $loads, bool $errors): void {
    measuring();
    $setUp();
    $visit = $signedIn ? $this->actingAs(User::factory()->create()) : $this;

    $response = $visit->get($uri)->assertOk();
    $html = (string) $response->getContent();
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect(str_contains($html, 'analytics-config'))->toBe($loads)
        ->and(str_contains($html, 'error-tracking-config'))->toBe($errors)
        ->and(str_contains($html, 'phc_'))->toBe($loads || $errors)
        ->and(str_contains($policy, PH_HOST))->toBe($loads || $errors)
        ->and(str_contains($policy, 'posthog.com'))->toBe($loads || $errors);
})->with([
    'the landing with a token' => [fn () => null, '/', false, true, false],
    'the landing without a token' => [fn () => measuring(''), '/', false, false, false],
    'the landing without consent' => [fn () => measuring(consent: 'denied'), '/', false, false, false],
    'the landing with a session' => [fn () => null, '/', true, false, false],
    'the privacy notice' => [fn () => null, '/privacy-notice', false, false, true],
    'the login' => [fn () => null, '/admin/login', false, false, true],
    'the login without a token' => [fn () => measuring(''), '/admin/login', false, false, false],
]);

it('reports browser errors on every page but the landing, with only a token: consent is about measuring visitors', function (string $uri, ?string $token): void {
    measuring(consent: 'denied');

    expect(Analytics::errorToken(matchedRequest($uri)))->toBe($token);
})->with([
    'the landing' => ['/', null],
    'the privacy notice' => ['/privacy-notice', PH_TOKEN],
    'the login' => ['/admin/login', PH_TOKEN],
    'a survey link' => ['/survey/some-token', PH_TOKEN],
]);

it('reports no browser error without a well-formed token', function (): void {
    measuring('phc_abc');

    expect(Analytics::errorToken(matchedRequest('/admin/login')))->toBeNull();
});

/** The block of the error reports a page hands the browser, decoded. */
function errorConfigIn(string $html): ?array
{
    if (preg_match('/<script type="application\/json" id="error-tracking-config">(.*?)<\/script>/s', $html, $match) !== 1) {
        return null;
    }

    return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
}

it('hands the browser of any other page only the token and the host for its error reports', function (): void {
    measuring(consent: 'denied');

    expect(errorConfigIn((string) $this->get('/admin/login')->assertOk()->getContent()))->toBe(['token' => PH_TOKEN, 'host' => PH_HOST]);
});

it('has no measurement block on a page that takes a token or a code in its address, and its error block never carries the address', function (): void {
    measuring();

    foreach (['/c/NO-SUCH-CODE', '/workers/NO-SUCH-TOKEN', '/survey/NO-SUCH-TOKEN', '/report/NO-SUCH-TOKEN', '/engagement/NO-SUCH-TOKEN', '/admin/invitations/NO-SUCH-TOKEN'] as $uri) {
        $html = (string) $this->get($uri)->getContent();

        expect($html)->not->toContain('analytics-config')
            ->and(errorConfigIn($html))->toBe(['token' => PH_TOKEN, 'host' => PH_HOST]);
    }
});

it('flashes the id of a remembered lead to the landing once, and nothing when none was remembered', function (): void {
    $this->startSession();

    Analytics::flashLead();
    expect(session()->pull(SessionKey::FLASH_DATA, []))->toBe([]);

    Analytics::rememberLead('9d3f6a52-8a7c-4d49-9d0e-5f3a1b2c4d5e');
    Analytics::flashLead();
    expect(session()->pull(SessionKey::FLASH_DATA, []))->toBe(['lead' => ['id' => '9d3f6a52-8a7c-4d49-9d0e-5f3a1b2c4d5e']]);

    Analytics::flashLead();
    expect(session()->pull(SessionKey::FLASH_DATA, []))->toBe([]);
});

it('never writes the value of a PostHog cookie, sent raw to the complaints page, in any log line', function (): void {
    $path = storage_path('logs/analytics-'.Str::uuid().'.log');
    config(['logging.channels.single.path' => $path, 'logging.default' => 'single']);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');

    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $owner = memberOf($tenant, TenantRole::Owner);
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner->id]);

    $cookie = ['HTTP_COOKIE' => 'ph_phc_ABC123_posthog=%7B%22distinct_id%22%3A%22SENTINEL-DISTINCT-ID%22%7D'];

    $this->call('GET', '/c/'.$channel->access_code, server: $cookie)->assertOk();
    $this->call('GET', '/c/NO-SUCH-CODE', server: $cookie)->assertNotFound();
    $this->call('POST', '/c/'.$channel->access_code, ['body' => ''], server: $cookie);

    $log = File::exists($path) ? File::get($path) : '';
    File::delete($path);

    expect($log)->not->toContain('SENTINEL')
        ->and($log)->not->toContain('ph_phc_');
});
