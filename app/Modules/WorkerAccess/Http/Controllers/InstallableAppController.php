<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The installable app of a company's people: what the browser reads to put their pages on the phone's home screen. No
 * account, no session and no cookie (the routes carry none): only what the company's own entry already shows.
 */
final readonly class InstallableAppController
{
    /** The page's own background (app.blade.php), so the phone paints no flash of another color while the app opens. */
    private const string BACKGROUND = '#FBFDFC';

    public function __construct(private Companies $companies) {}

    /**
     * The manifest of the company of the slug. The app is one per company, inside `/me/{slug}`; the icon opens Marcar when
     * the company has the registro de jornada (without the module, Marcar answers 404) and the person's start otherwise.
     */
    public function manifest(string $tenant): JsonResponse
    {
        $company = $this->companies->current();
        $home = route('worker.home', $tenant, absolute: false);
        $punch = route('attendance.device.punch.show', $tenant, absolute: false);

        return response()->json([
            'id' => $punch,
            'scope' => $home,
            'start_url' => $company->allows('attendance') ? $punch : $home,
            'name' => $company->tradeName,
            'short_name' => Str::limit($company->tradeName, 12, ''),
            'lang' => 'es-MX',
            'dir' => 'ltr',
            'display' => 'standalone',
            'background_color' => self::BACKGROUND,
            'theme_color' => self::BACKGROUND,
            'icons' => [
                ['src' => '/apple-touch-icon-180.png', 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/app-icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * The service worker of the company's app: the file `npm run build:sw` compiled, as it is (one classic file with no
     * import: served from `/me/{slug}/sw.js`, an import would resolve against that address and the worker would never
     * activate). The version comes in the address (`?v=`, the page's revision) and is ignored here: it only makes the
     * browser see a new script. The scope is wider than the file's own folder, hence `Service-Worker-Allowed`. With
     * `PWA_SW_KILL` the answer is a worker that undoes itself instead; it never touches the delivery of marks.
     */
    public function serviceWorker(string $tenant): Response
    {
        $worker = config()->boolean('worker_access.pwa_sw_kill') ? $this->shutdown($tenant) : File::get(public_path('build/pwa/sw.js'));

        return response($worker, headers: [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => route('worker.home', $tenant, absolute: false),
        ]);
    }

    /**
     * The shell the worker keeps (`/me/{slug}/offline`) and the phone opens when there is no network: a static page that
     * names the company and the way back to the person's start. Never a person, a session, a CSRF token nor the time.
     */
    public function offline(string $tenant): View
    {
        return view('worker-access.offline', [
            'company' => $this->companies->current()->tradeName,
            'homeUrl' => route('worker.home', $tenant, absolute: false),
            'swKill' => config()->boolean('worker_access.pwa_sw_kill'),
        ]);
    }

    /**
     * A worker that takes the company's caches out, unregisters itself and reloads the open pages, with no `fetch` handler:
     * generated here (not compiled) so the slug it names is the company's. Only this company's caches (`acme` leaves
     * those of `acme-industrial` alone: what follows the prefix is a revision, which has no hyphen).
     */
    private function shutdown(string $tenant): string
    {
        $prefix = json_encode("humetria-pwa-{$tenant}-", JSON_THROW_ON_ERROR);

        return <<<JS
            self.addEventListener('install', () => { self.skipWaiting(); });
            self.addEventListener('activate', (event) => {
                event.waitUntil((async () => {
                    const prefix = {$prefix};
                    for (const key of await caches.keys()) {
                        if (key.startsWith(prefix) && !key.slice(prefix.length).includes('-')) {
                            await caches.delete(key);
                        }
                    }
                    await self.registration.unregister();
                    for (const client of await self.clients.matchAll({ type: 'window' })) {
                        client.navigate(client.url);
                    }
                })());
            });

            JS;
    }
}
