<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Exceptions\PageUnavailable;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Support\OpenedPage;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Opens the page of the workers whose link carries `$token`, inside its company (criterio C-55). It opens while the link is
 * the one in force (RH did not replace it), a key still kept derives it, RH did not switch it off, its day did not pass and the
 * company may be used. Nothing about who opens it is kept.
 */
final readonly class OpenWorkersPage
{
    public function __construct(private Companies $companies) {}

    /**
     * @throws PageUnavailable
     */
    public function handle(string $token): OpenedPage
    {
        // Read past the company scope: until the link is known, no company is.
        $link = PageLink::query()->withoutGlobalScopes()->where('token_hash', PageLink::hashToken($token))->first();

        // The hash finds the row; a key still kept must derive it (a link of a dropped key is revoked).
        if ($link === null || $link->token() !== $token || $link->status() !== PageLinkStatus::Active) {
            throw new PageUnavailable;
        }

        $company = $this->companies->enter($link->tenant_id);

        if ($company->blocked) {
            throw new PageUnavailable;
        }

        return new OpenedPage($company->id, $company->tradeName);
    }
}
