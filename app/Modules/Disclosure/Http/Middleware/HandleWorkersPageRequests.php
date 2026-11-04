<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia for the public page of the workers: it knows no user, no company switcher and no session, so it shares nothing but
 * the app's name. (The panel's HandleInertiaRequests reads the signed-in person and the cookies: someone from RH opening the
 * poster must see what anybody sees.)
 */
final class HandleWorkersPageRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['name' => config('app.name')];
    }
}
