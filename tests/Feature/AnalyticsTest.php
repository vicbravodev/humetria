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

const GA_ID = 'G-ABC123XYZ9';

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

function measuring(string $id = GA_ID, string $consent = 'granted'): void
{
    config(['services.google_analytics.measurement_id' => $id, 'services.google_analytics.consent_default' => $consent]);
}

it('measures the landing for a visitor without a session when there is an id and consent', function (): void {
    measuring();

    expect(Analytics::measurementId(matchedRequest()))->toBe(GA_ID);
});

it('measures nothing without an id, without consent, outside the landing or for someone signed in', function (Closure $setUp, string $uri, bool $signedIn): void {
    measuring();
    $setUp();

    expect(Analytics::measurementId(matchedRequest($uri, $signedIn ? User::factory()->create() : null)))->toBeNull();
})->with([
    'no id' => [fn () => measuring(''), '/', false],
    'consent denied' => [fn () => measuring(consent: 'denied'), '/', false],
    'another page' => [fn () => null, '/privacy-notice', false],
    'the login' => [fn () => null, '/admin/login', false],
    'a session' => [fn () => null, '/', true],
]);

it('accepts only a well-formed GA4 id', function (string $id, bool $valid): void {
    measuring($id);

    expect(Analytics::measurementId(matchedRequest()))->toBe($valid ? $id : null);
})->with([
    'ten characters' => ['G-ABC123XYZ9', true],
    'short' => ['G-A', true],
    'lowercase' => ['G-abc123', false],
    'universal analytics' => ['UA-123456-1', false],
    'only the prefix' => ['G-', false],
    'a space around' => [' G-ABC123', false],
    'a trailing newline' => ["G-ABC123\n", false],
    'markup' => ['G-ABC"></script><script>x', false],
    'a query' => ['G-ABC123&x=1', false],
]);

it('gives Google only the origin of a referrer from this very site, and nothing it can read by itself', function (?string $referer, ?string $expected): void {
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

it('tells Google the page without its personal query: only the utm parameters stay', function (string $uri, string $expected): void {
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

it('writes the gtag loader and its configuration with the nonce of the policy, consent before config', function (): void {
    measuring();

    $response = $this->get(route('home'));
    $html = (string) $response->assertOk()->getContent();
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
    $nonce = $match[1] ?? '';

    expect($html)->toContain('<script async src="https://www.googletagmanager.com/gtag/js?id='.GA_ID.'" nonce="'.$nonce.'"></script>')
        ->and($html)->toContain('window.dataLayer = window.dataLayer || [];')
        ->and($html)->toContain('function gtag()')
        ->and($html)->toContain('"allow_google_signals":false')
        ->and($html)->toContain('"allow_ad_personalization_signals":false')
        ->and(preg_match_all('/<script(?![^>]*type="application\/json")[^>]*>/', $html, $scripts))->toBeGreaterThan(2)
        ->and($scripts[0])->each->toContain('nonce="'.$nonce.'"')
        ->and(strpos($html, "gtag('consent', 'default'"))->toBeInt()
        ->and(strpos($html, "gtag('consent', 'default'"))->toBeLessThan(strpos($html, "gtag('config'"))
        ->and(strpos($html, "gtag('config'"))->toBeInt();
});

it('keeps advertising signals and advertising storage off in the consent default', function (): void {
    measuring();

    $html = landingHtml();

    expect($html)->toMatch("/gtag\('consent', 'default', .*\"ad_storage\":\"denied\".*\"ad_user_data\":\"denied\".*\"ad_personalization\":\"denied\"/s")
        ->and($html)->toMatch("/gtag\('consent', 'default', .*\"analytics_storage\":\"granted\"/s");
});

it('tells Google the page without its personal query and no referrer unless it is a page of this site', function (): void {
    measuring();

    $html = landingHtml('/?token=SECRET-TOKEN&utm_source=google&email=a@b.mx', 'https://www.google.com/search?q=SECRET-SEARCH');

    // The page's own Inertia JSON carries its url (it is not Analytics): only what Analytics writes is judged.
    $analytics = substr($html, (int) strpos($html, 'googletagmanager.com'), (int) strpos($html, '</script>', (int) strpos($html, "gtag('config'")) - (int) strpos($html, 'googletagmanager.com'));

    expect($analytics)->toContain('"page_location":"http:\/\/localhost\/?utm_source=google"')
        ->and($analytics)->not->toContain('SECRET-TOKEN')
        ->and($analytics)->not->toContain('SECRET-SEARCH')
        ->and($analytics)->not->toContain('a@b.mx')
        ->and($analytics)->not->toContain('page_referrer');
});

it('sends a referrer from this site as its origin only: a link with a token never reaches Google', function (): void {
    measuring();

    $html = landingHtml('/', 'http://localhost/encuesta/TOKEN-OF-A-SURVEY?x=1');

    $analytics = substr($html, (int) strpos($html, 'googletagmanager.com'), (int) strpos($html, '</script>', (int) strpos($html, "gtag('config'")) - (int) strpos($html, 'googletagmanager.com'));

    expect($analytics)->toContain('"page_referrer":"http:\/\/localhost\/"')
        ->and($analytics)->not->toContain('TOKEN-OF-A-SURVEY');
});

it('has no script, no dataLayer and no Google origin in the policy unless the four conditions hold', function (Closure $setUp, string $uri, bool $signedIn, bool $loads): void {
    measuring();
    $setUp();
    $visit = $signedIn ? $this->actingAs(User::factory()->create()) : $this;

    $response = $visit->get($uri)->assertOk();
    $html = (string) $response->getContent();
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect(str_contains($html, 'googletagmanager.com'))->toBe($loads)
        ->and(str_contains($html, 'dataLayer'))->toBe($loads)
        ->and(str_contains($policy, 'googletagmanager.com'))->toBe($loads)
        ->and(str_contains($policy, 'google-analytics.com'))->toBe($loads);
})->with([
    'the landing with an id' => [fn () => null, '/', false, true],
    'the landing without an id' => [fn () => measuring(''), '/', false, false],
    'the landing without consent' => [fn () => measuring(consent: 'denied'), '/', false, false],
    'the landing with a session' => [fn () => null, '/', true, false],
    'the privacy notice' => [fn () => null, '/privacy-notice', false, false],
    'the login' => [fn () => null, '/admin/login', false, false],
]);

it('has no script on a page that takes a token or a code in its address', function (): void {
    measuring();

    foreach (['/c/NO-SUCH-CODE', '/workers/NO-SUCH-TOKEN', '/survey/NO-SUCH-TOKEN', '/report/NO-SUCH-TOKEN', '/portal/NO-SUCH-TOKEN', '/engagement/NO-SUCH-TOKEN', '/admin/invitations/NO-SUCH-TOKEN'] as $uri) {
        $response = $this->get($uri);

        expect((string) $response->getContent())->not->toContain('googletagmanager.com')
            ->and((string) $response->getContent())->not->toContain('dataLayer')
            ->and((string) $response->headers->get('Content-Security-Policy'))->not->toContain('google');
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

it('never writes the value of a _ga cookie, sent raw to the complaints page, in any log line', function (): void {
    $path = storage_path('logs/analytics-'.Str::uuid().'.log');
    config(['logging.channels.single.path' => $path, 'logging.default' => 'single']);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');

    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $owner = memberOf($tenant, TenantRole::Owner);
    $channel = ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $owner->id]);

    $cookie = ['HTTP_COOKIE' => '_ga=GA1.1.SENTINEL-CLIENT-ID.1700000000; _ga_ABC123XYZ9=GS2.1.SENTINEL-SESSION'];

    $this->call('GET', '/c/'.$channel->access_code, server: $cookie)->assertOk();
    $this->call('GET', '/c/NO-SUCH-CODE', server: $cookie)->assertNotFound();
    $this->call('POST', '/c/'.$channel->access_code, ['body' => ''], server: $cookie);

    $log = File::exists($path) ? File::get($path) : '';
    File::delete($path);

    expect($log)->not->toContain('SENTINEL')
        ->and($log)->not->toContain('GA1.1');
});
