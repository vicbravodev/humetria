<?php

declare(strict_types=1);

/*
| WorkerAccess: the identity of the person who works (no account): their keys (passkeys) and, later, their session.
*/
return [

    /*
    | Seconds a passkey challenge (to register a key or to sign) is good for.
    */
    'webauthn' => [
        'challenge_seconds' => (int) env('WORKER_ACCESS_WEBAUTHN_CHALLENGE_SECONDS', 60),
    ],

];
