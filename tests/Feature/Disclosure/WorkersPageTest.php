<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\OpenWorkersPage;
use App\Modules\Disclosure\Actions\RotatePageLink;
use App\Modules\Disclosure\Http\Controllers\WorkersPageController;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenWorkersPage::class, WorkersPageController::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Acme Manufacturas S.A. de C.V.']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    workIn($this->tenant);
    $this->token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();
});

/** A published policy reaching `$centers`. @param list<WorkCenter> $centers */
function workersPolicy(array $centers, int $version = 1): Document
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => test()->tenant->id, 'version' => $version, 'published_by' => test()->hr->id]);

    foreach ($centers as $center) {
        DocumentCenter::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);
    }

    return $policy;
}

/** What a page says without its address: the part that must not tell a missing link from a revoked one. */
function inactiveFingerprint(Illuminate\Testing\TestResponse $response): array
{
    $page = $response->viewData('page');

    return ['status' => $response->status(), 'component' => $page['component'], 'props' => $page['props']];
}

it('lives at /workers/{token}, with its centers under it, and is named', function (): void {
    expect(route('workers.show', 'abc', absolute: false))->toBe('/workers/abc')
        ->and(route('workers.center', ['abc', 'def'], absolute: false))->toBe('/workers/abc/centers/def');
});

it('lists the centers in use by name, ten at a time, with nothing but their names', function (): void {
    WorkCenter::factory()->for($this->tenant)->archived()->create(['name' => 'Planta Cerrada']);

    $this->get(route('workers.show', $this->token))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure-public/centers')
            ->where('company', 'Acme')
            ->where('token', $this->token)
            ->where('centers.total', 2)
            ->where('centers.per_page', 10)
            ->where('centers.data', [['id' => $this->north->id, 'name' => 'Planta Norte'], ['id' => $this->south->id, 'name' => 'Planta Sur']])
            ->where('filters.q', ''));
});

it('searches the centers on the server, ignoring case and accents', function (): void {
    WorkCenter::factory()->for($this->tenant)->create(['name' => 'Bodega Querétaro']);

    $this->get(route('workers.show', [$this->token, 'q' => 'QUERETARO']))
        ->assertInertia(fn (Assert $page) => $page->where('centers.total', 1)->where('centers.data.0.name', 'Bodega Querétaro')->where('filters.q', 'QUERETARO'));
});

it('goes straight to the center when the company has only one', function (): void {
    $this->south->update(['archived_at' => now()]);

    $this->get(route('workers.show', $this->token))->assertRedirect(route('workers.center', [$this->token, $this->north->id]));
});

it('shows a center the policy in force there, with the company name in place', function (): void {
    workersPolicy([$this->north]);

    $this->get(route('workers.center', [$this->token, $this->north->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure-public/center')
            ->where('company', 'Acme')
            ->where('center', ['id' => $this->north->id, 'name' => 'Planta Norte'])
            ->where('policy.version', 1)
            ->where('policy.publishedOn', '20 nov 2026')
            ->where('policy.sections.0.text', fn ($text) => str_contains($text, 'Acme Manufacturas S.A. de C.V.') && ! str_contains($text, '{legal_name}')));
});

it('shows each center the version in force there, and none where there is none', function (): void {
    workersPolicy([$this->north, $this->south], 1);
    workersPolicy([$this->south], 2);
    $bare = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Nueva']);

    $this->get(route('workers.center', [$this->token, $this->north->id]))->assertInertia(fn (Assert $page) => $page->where('policy.version', 1));
    $this->get(route('workers.center', [$this->token, $this->south->id]))->assertInertia(fn (Assert $page) => $page->where('policy.version', 2));
    $this->get(route('workers.center', [$this->token, $bare->id]))->assertInertia(fn (Assert $page) => $page->where('policy', null));
});

it('is the same neutral 404 for every reason the link or the center does not open, with nothing that tells them apart', function (Closure $arrange): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $reference = $this->get(route('workers.center', [str_repeat('a', 43), $this->north->id]));
    [$url, $kind] = $arrange($this, $foreign, $archived);

    $response = $this->get($url);

    expect(inactiveFingerprint($response))->toEqual(inactiveFingerprint($reference))
        ->and($response->status())->toBe(404)
        ->and($kind)->toBeString();
    $response->assertInertia(fn (Assert $page) => $page->component('disclosure-public/inactive')->missing('company'));
})->with([
    'a made-up link' => [fn ($test) => [route('workers.show', str_repeat('b', 43)), 'link']],
    'a rotated link' => [function ($test) {
        $old = $test->token;
        resolve(RotatePageLink::class)->handle($test->hr->id);

        return [route('workers.show', $old), 'link'];
    }],
    'a switched off link' => [function ($test) {
        PageLink::query()->update(['disabled_at' => now()]);

        return [route('workers.center', [$test->token, $test->north->id]), 'link'];
    }],
    'an expired link' => [function ($test) {
        PageLink::query()->update(['expires_at' => now()->addSecond()]);
        $test->travelTo(now()->addSeconds(2));

        return [route('workers.show', $test->token), 'link'];
    }],
    'a blocked company' => [function ($test) {
        Tenant::query()->whereKey($test->tenant->id)->update(['deactivated_at' => now()]);

        return [route('workers.show', $test->token), 'company'];
    }],
    'a center of another company' => [fn ($test, $foreign) => [route('workers.center', [$test->token, $foreign->id]), 'center']],
    'an archived center' => [fn ($test, $foreign, $archived) => [route('workers.center', [$test->token, $archived->id]), 'center']],
    'a center that does not exist' => [fn ($test) => [route('workers.center', [$test->token, '0194c1de-0000-7000-8000-000000000000']), 'center']],
    'a center that is not a uuid' => [fn ($test) => [url("workers/{$test->token}/centers/x"), 'center']],
]);

it('opens a second before the expiry and not at it', function (): void {
    PageLink::query()->update(['expires_at' => '2026-11-20 18:00:01']);

    $this->get(route('workers.show', $this->token))->assertOk();

    $this->travelTo('2026-11-20 18:00:01');
    $this->get(route('workers.show', $this->token))->assertNotFound();
});

it('opens with a key still kept and not with one that was dropped', function (): void {
    $previous = 'base64:'.base64_encode(str_repeat('p', 32));
    $old = PageLink::tokenFor($this->tenant->id, 1, $previous);
    PageLink::query()->update(['token_hash' => PageLink::hashToken($old)]);

    $this->get(route('workers.show', $old))->assertNotFound();

    config()->set('app.previous_keys', [$previous]);
    $this->get(route('workers.show', $old))->assertOk();
});

it('sends the headers of a page that is not to be kept, indexed or followed, and a policy with no outside origin', function (): void {
    $response = $this->get(route('workers.show', $this->token));

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('nonce-')->not->toMatch('#https?://#')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    $this->get(route('workers.show', str_repeat('z', 43)))->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
});

it('is a page that asks the browser not to index it, in its markup too', function (): void {
    $this->get(route('workers.show', $this->token))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('starts no session and sets no cookie, so nobody is recorded for opening it', function (): void {
    $response = $this->get(route('workers.show', $this->token));

    expect($response->headers->getCookies())->toBe([])
        ->and(DB::table('sessions')->count())->toBe(0);
    $this->assertGuest();
});

it('knows nobody who is signed in: a person of RH opening the poster gets what anybody gets', function (): void {
    $this->actingAs($this->hr)->get(route('workers.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->missing('auth')->missing('tenant')->missing('tenants')->where('name', config('app.name')));
});

it('hands the page nothing of a person: no names, emails, ids, tokens of others or the owner\'s address', function (): void {
    workersPolicy([$this->north]);
    $props = $this->get(route('workers.center', [$this->token, $this->north->id]))->viewData('page')['props'];
    $json = json_encode($props) ?: '';

    expect($json)->not->toContain($this->hr->email)->not->toContain($this->hr->name)->not->toContain($this->tenant->id)
        ->and(array_keys($props))->toEqualCanonicalizing(['name', 'token', 'company', 'center', 'others', 'policy', 'blocks', 'results']);
});

it('hands the list of centers only their names and ids', function (): void {
    $props = $this->get(route('workers.show', $this->token))->viewData('page')['props'];
    $json = json_encode($props) ?: '';

    expect($json)->not->toContain($this->hr->email)->not->toContain($this->hr->name)->not->toContain($this->tenant->id)
        ->and(array_keys($props))->toEqualCanonicalizing(['name', 'token', 'company', 'centers', 'filters'])
        ->and($props['centers']['data'])->each->toHaveKeys(['id', 'name']);
});

it('limits one address to 60 requests a minute and does not limit another', function (): void {
    foreach (range(1, 60) as $ignored) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(route('workers.show', $this->token))->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(route('workers.show', $this->token))->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get(route('workers.show', $this->token))->assertOk();
});

it('lets a whole plant open the same poster from a hundred addresses', function (): void {
    foreach (range(1, 100) as $address) {
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$address}"])->get(route('workers.show', $this->token))->assertOk();
    }
});

it('counts the requests of a link whether it opens or not, so scraping with wrong links is limited too', function (): void {
    foreach (range(1, 60) as $number) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])->get(route('workers.show', str_repeat(dechex($number % 16), 43)))->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])->get(route('workers.show', $this->token))->assertStatus(429);
});

it('reads its limits from the configuration', function (): void {
    expect(Route::getRoutes()->getByName('workers.show')?->gatherMiddleware())->toContain('throttle:workers-page')
        ->and(Route::getRoutes()->getByName('workers.center')?->gatherMiddleware())->toContain('throttle:workers-page')
        ->and(Illuminate\Support\Facades\RateLimiter::limiter('workers-page'))->not->toBeNull();
});
