<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\PageLink;

/**
 * The address a person opens to read the page of the workers: the one RH prints. Null when the link can no longer be derived
 * (its app key was dropped and it has to be rotated).
 */
final class PageUrl
{
    public static function of(PageLink $link): ?string
    {
        $token = $link->token();

        return $token === null ? null : url("workers/{$token}");
    }
}
