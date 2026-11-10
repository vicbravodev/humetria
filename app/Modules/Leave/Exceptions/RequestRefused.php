<?php

declare(strict_types=1);

namespace App\Modules\Leave\Exceptions;

use RuntimeException;

/**
 * A request that cannot be made or approved, with the reason as a key: each screen says it in its own words.
 */
final class RequestRefused extends RuntimeException
{
    /** What the person who approves reads, as "usted"; the other reasons are worded by the screen that asks. */
    private const array MESSAGES = ['insufficient_balance' => 'No tiene días disponibles y su política no permite adelantar vacaciones.'];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? "The request was refused: {$reason}.");
    }
}
