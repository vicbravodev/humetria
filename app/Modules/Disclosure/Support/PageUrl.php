<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\PageLink;

/**
 * The address a person opens to read the page of the workers: the one RH prints, or straight to a center's own page. Null when the link can no longer be derived
 * (its app key was dropped and it has to be rotated).
 */
final class PageUrl
{
    public static function of(PageLink $link, ?string $workCenterId = null): ?string
    {
        $token = $link->token();

        return match (true) {
            $token === null => null,
            $workCenterId === null => route('workers.show', $token),
            default => route('workers.center', [$token, $workCenterId]),
        };
    }
}
