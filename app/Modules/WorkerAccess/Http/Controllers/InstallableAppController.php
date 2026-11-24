<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Http\JsonResponse;
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
}
