<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The load balancers or reverse proxies whose X-Forwarded-* headers are
    | believed (scheme, host, port and client IP). A comma-separated list of
    | IPs or CIDR ranges, or "*" to trust whoever calls. Unset trusts none,
    | so nobody can fake HTTPS or their IP by sending the header themselves.
    | Laravel's own TrustProxies middleware (global stack) reads this key.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
