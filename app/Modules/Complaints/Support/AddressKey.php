<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use Illuminate\Http\Request;

/**
 * What the limits of the public routes are keyed by: a keyed hash of the address, never the address (criterio C-46). The key
 * lives only in the limiter's cache, which expires it within a day; with it nobody can tell whose address it was without
 * the application key.
 */
final class AddressKey
{
    public static function for(Request $request): string
    {
        return 'complaints:'.hash_hmac('sha256', $request->ip() ?? '', config()->string('app.key'));
    }
}
