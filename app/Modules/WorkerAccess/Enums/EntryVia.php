<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Enums;

/**
 * How a person entered their session: with their key, with a link RH issued (which may register a key), or with a link they
 * asked for (which never does).
 */
enum EntryVia: string
{
    case Key = 'key';
    case IssuedLink = 'issued_link';
    case RequestedLink = 'requested_link';
}
