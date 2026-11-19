<?php

declare(strict_types=1);

return [
    /*
    | The identity provider of Humetría's own staff (superadmin, support), one for the whole platform. Companies configure
    | theirs from the panel; this one comes from the environment. Empty = staff single sign-on is off.
    */
    'staff' => [
        'issuer' => env('SSO_STAFF_ISSUER', ''),
        'client_id' => env('SSO_STAFF_CLIENT_ID', ''),
        'client_secret' => env('SSO_STAFF_CLIENT_SECRET', ''),
        // Only emails at this domain may come in, however the provider vouches for them.
        'domain' => env('SSO_STAFF_DOMAIN', ''),
    ],
];
