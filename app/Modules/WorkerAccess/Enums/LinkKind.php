<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Enums;

/**
 * A one-time link: `issued` by RH (48 hours; it lets the person register a key) or `requested` by the person themselves
 * (30 minutes; it only opens a session).
 */
enum LinkKind: string
{
    case Issued = 'issued';
    case Requested = 'requested';

    /** How a session opened with a link of this kind entered. */
    public function entry(): EntryVia
    {
        return $this === self::Issued ? EntryVia::IssuedLink : EntryVia::RequestedLink;
    }
}
