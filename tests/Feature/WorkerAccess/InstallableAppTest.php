<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Http\Controllers\InstallableAppController;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

mutates(InstallableAppController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']);
    workIn($this->tenant);
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
});

describe('the manifest', function (): void {
    it('is the company\'s own: Marcar opens from the icon, inside the person\'s pages', function (): void {
        $response = $this->get(route('worker.app.manifest', 'acme'))->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('application/manifest+json')
            ->and($response->json('id'))->toBe('/me/acme/punch')
            ->and($response->json('scope'))->toBe('/me/acme')
            ->and($response->json('start_url'))->toBe('/me/acme/punch')
            ->and($response->json('name'))->toBe('Acme Industrial')
            ->and($response->json('short_name'))->toBe('Acme Industr')
            ->and($response->json('lang'))->toBe('es-MX')
            ->and($response->json('dir'))->toBe('ltr')
            ->and($response->json('display'))->toBe('standalone')
            ->and($response->json('background_color'))->toBe('#FBFDFC')
            ->and($response->json('theme_color'))->toBe('#FBFDFC');
    });

    it('opens the person\'s start when the company has no registro de jornada, where Marcar would answer 404', function (): void {
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

        $response = $this->get(route('worker.app.manifest', 'acme'))->assertOk();

        expect($response->json('start_url'))->toBe('/me/acme')
            ->and($response->json('id'))->toBe('/me/acme/punch');
    });

    it('lists four icons and every file exists', function (): void {
        $icons = $this->get(route('worker.app.manifest', 'acme'))->json('icons');

        expect($icons)->toHaveCount(4)
            ->and(collect($icons)->pluck('purpose')->all())->toBe(['any', 'any', 'any', 'maskable']);

        foreach ($icons as $icon) {
            expect(public_path(ltrim((string) $icon['src'], '/')))->toBeFile();
        }
    });

    it('tells two companies apart by name and by address', function (): void {
        $other = Tenant::factory()->create(['trade_name' => 'Boreal', 'slug' => 'boreal']);
        resolve(CompanyModules::class)->syncPlan($other->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

        $mine = $this->get(route('worker.app.manifest', 'acme'))->json();
        $theirs = $this->get(route('worker.app.manifest', 'boreal'))->json();

        expect($theirs['name'])->toBe('Boreal')
            ->and($theirs['scope'])->toBe('/me/boreal')
            ->and($theirs['start_url'])->toBe('/me/boreal/punch')
            ->and($mine['name'])->toBe('Acme Industrial')
            ->and($mine['scope'])->toBe('/me/acme');
    });

    it('carries nothing of anyone: no cookie in, no cookie out, the same for a person with a session', function (): void {
        $visitor = $this->get(route('worker.app.manifest', 'acme'))->assertOk()->assertHeaderMissing('Set-Cookie');
        $withSession = $this->withCookie(config()->string('session.cookie'), 'a-session-id')->get(route('worker.app.manifest', 'acme'))
            ->assertOk()->assertHeaderMissing('Set-Cookie');

        expect($withSession->getContent())->toBe($visitor->getContent())
            ->and($visitor->getContent())->not->toContain('Ana');
    });

    it('answers the same 404 for a company that does not exist and for a blocked one', function (): void {
        Tenant::factory()->create(['slug' => 'blocked', ...suspension()]);

        $this->get(route('worker.app.manifest', 'nadie'))->assertNotFound();
        $this->get(route('worker.app.manifest', 'blocked'))->assertNotFound();
    });
});

/** The compiled worker the build wrote (`npm run build:sw`). */
function compiledWorker(): string
{
    return (string) file_get_contents(public_path('build/pwa/sw.js'));
}

describe('the service worker', function (): void {
    it('is the compiled file, served from the scope it covers, never cached by the browser', function (): void {
        $response = $this->get(route('worker.app.sw', 'acme'))->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
            ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
            ->and($response->headers->get('Service-Worker-Allowed'))->toBe('/me/acme')
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
            ->and($response->headers->get('Content-Security-Policy'))->not->toBeNull()
            ->and($response->getContent())->toBe(compiledWorker());
    });

    it('is one classic file: an import or an export would never activate it from /me/{slug}/sw.js', function (): void {
        expect(compiledWorker())->not->toMatch('/^\s*(import|export)\b/m')
            ->and(compiledWorker())->not->toContain('import(')
            ->and(compiledWorker())->not->toContain('import.meta');
    });

    it('is the same for any revision: the address says the version, the file does not', function (): void {
        expect($this->get(route('worker.app.sw', ['acme', 'v' => 'abc']))->getContent())->toBe($this->get(route('worker.app.sw', 'acme'))->getContent());
    });

    it('carries nothing of anyone: no cookie in, none out', function (): void {
        $this->get(route('worker.app.sw', 'acme'))->assertOk()->assertHeaderMissing('Set-Cookie');
        $this->withCookie(config()->string('session.cookie'), 'a-session-id')->get(route('worker.app.sw', 'acme'))->assertOk()->assertHeaderMissing('Set-Cookie');
        expect(DB::table('sessions')->count())->toBe(0);
    });

    it('answers the same 404 for a company that does not exist and for a blocked one', function (): void {
        Tenant::factory()->create(['slug' => 'blocked', ...suspension()]);

        $this->get(route('worker.app.sw', 'nadie'))->assertNotFound();
        $this->get(route('worker.app.sw', 'blocked'))->assertNotFound();
    });

    it('switched off, is a worker that undoes itself: it drops this company\'s caches and unregisters', function (): void {
        config(['worker_access.pwa_sw_kill' => true]);

        $response = $this->get(route('worker.app.sw', 'acme'))->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
            ->and($response->headers->get('Cache-Control'))->toContain('no-cache')
            ->and($response->headers->get('Service-Worker-Allowed'))->toBe('/me/acme')
            ->and($response->getContent())->toContain('unregister')
            ->and($response->getContent())->toContain('caches.delete')
            ->and($response->getContent())->toContain('humetria-pwa-acme-')
            ->and($response->getContent())->not->toContain("addEventListener('fetch'")
            ->and($response->getContent())->not->toContain(compiledWorker());
    });

    it('switched off, names only its own company\'s caches', function (): void {
        Tenant::factory()->create(['slug' => 'boreal']);
        config(['worker_access.pwa_sw_kill' => true]);

        $content = $this->get(route('worker.app.sw', 'boreal'))->getContent();

        expect($content)->toContain('humetria-pwa-boreal-')
            ->and($content)->not->toContain('humetria-pwa-acme-');
    });

    it('switched off, never switches off the delivery of marks: an empty batch is still a 422, not a 404 or a 503', function (): void {
        config(['worker_access.pwa_sw_kill' => true]);

        $this->postJson(route('attendance.device.punch.offline', 'acme'), ['items' => []])->assertUnprocessable();
    });
});

describe('the shell', function (): void {
    it('is a page of the company with no inline script and its files from the build', function (): void {
        $html = $this->get(route('worker.app.offline', 'acme'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        expect($xpath->query('//script[not(@src)]')->length)->toBe(0)
            ->and($xpath->query('//script[@src]')->length)->toBeGreaterThan(0);

        foreach ($xpath->query('//script[@src]|//link[@rel="stylesheet" or @rel="modulepreload"]') as $node) {
            expect($node->getAttribute($node->nodeName === 'script' ? 'src' : 'href'))->toContain('/build/assets/');
        }

        $app = $xpath->query('//div[@id="offline-app"]')->item(0);
        expect($app->getAttribute('data-company'))->toBe('Acme Industrial')
            ->and($app->getAttribute('data-home-url'))->toBe(route('worker.home', 'acme', absolute: false))
            ->and($xpath->query('//meta[@name="pwa-sw-kill"]')->length)->toBe(0)
            ->and($xpath->query('//link[@rel="manifest"]/@href')->item(0)?->nodeValue)->toEndWith('/me/acme/manifest.webmanifest');
    });

    it('says the worker is switched off only when it is', function (): void {
        config(['worker_access.pwa_sw_kill' => true]);

        expect($this->get(route('worker.app.offline', 'acme'))->getContent())->toContain('<meta name="pwa-sw-kill" content="1">');
    });

    it('carries nothing of anyone: no name, no token, no cookie, the same with a session', function (): void {
        $visitor = $this->get(route('worker.app.offline', 'acme'))->assertOk()->assertHeaderMissing('Set-Cookie');
        $withSession = $this->withCookie(config()->string('session.cookie'), 'a-session-id')->get(route('worker.app.offline', 'acme'))
            ->assertOk()->assertHeaderMissing('Set-Cookie');

        // Each answer has its own CSP nonce, and a long-lived app (the test) preloads the fonts only the first time: nothing else differs.
        $same = fn (string $html): string => preg_replace(['/nonce="[^"]+"/', '/<link rel="preload"[^>]*>\s*/'], '', $html) ?? '';

        expect($same((string) $withSession->getContent()))->toBe($same((string) $visitor->getContent()))
            ->and($visitor->getContent())->not->toContain('csrf-token')
            ->and($visitor->getContent())->not->toContain('_token')
            ->and(DB::table('sessions')->count())->toBe(0);
    });

    it('answers the same 404 for a company that does not exist and for a blocked one', function (): void {
        Tenant::factory()->create(['slug' => 'blocked', ...suspension()]);

        $this->get(route('worker.app.offline', 'nadie'))->assertNotFound();
        $this->get(route('worker.app.offline', 'blocked'))->assertNotFound();
    });
});
