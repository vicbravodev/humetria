<?php

declare(strict_types=1);

/*
| WorkerAccess: the identity of the person who works (no account): their keys (passkeys), their one-time links and their
| session (spec «Identidad única de la persona trabajadora»).
*/
return [

    /*
    | Seconds a passkey challenge (to register a key or to sign) is good for.
    */
    'webauthn' => [
        'challenge_seconds' => (int) env('WORKER_ACCESS_WEBAUTHN_CHALLENGE_SECONDS', 60),
    ],

    /*
    | A person's session ends after these minutes without a request, and after these hours whatever they do.
    */
    'session' => [
        'idle_minutes' => (int) env('WORKER_ACCESS_SESSION_IDLE_MINUTES', 30),
        'absolute_hours' => (int) env('WORKER_ACCESS_SESSION_ABSOLUTE_HOURS', 8),
    ],

    /*
    | Hours a link RH issues is good for, and minutes a link the person asks for is good for. Each opens once.
    */
    'links' => [
        'issued_hours' => (int) env('WORKER_ACCESS_ISSUED_LINK_HOURS', 48),
        'requested_minutes' => (int) env('WORKER_ACCESS_REQUESTED_LINK_MINUTES', 30),
    ],

    /*
    | Minutes a signature (or a code) counts as a recent proof for what is delicate. A person without a key proves with a
    | code by email: it is good for these minutes and these tries, and a person (or a session) asks for at most this many
    | codes in that time.
    */
    'proof' => [
        'minutes' => (int) env('WORKER_ACCESS_PROOF_MINUTES', 5),
        'code_minutes' => (int) env('WORKER_ACCESS_PROOF_CODE_MINUTES', 10),
        'code_attempts' => (int) env('WORKER_ACCESS_PROOF_CODE_ATTEMPTS', 5),
        'code_sends' => (int) env('WORKER_ACCESS_PROOF_CODE_SENDS', 3),
    ],

    /*
    | Keys in use a person may have at once (a phone and a computer, say).
    */
    'max_keys' => (int) env('WORKER_ACCESS_MAX_KEYS', 3),

];
