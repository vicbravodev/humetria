<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;

/**
 * What the management screen of the page of the workers receives (criterio C-55): the link, its state and its dates. The link
 * is a credential: seeing it goes to the audit trail (without the token) and only whoever manages gets it.
 */
final readonly class PagePages
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @return array{link: array<string, mixed>|null}
     */
    public function show(): array
    {
        $link = PageLink::query()->first();

        if ($link === null) {
            return ['link' => null];
        }

        $this->audit->handle('disclosure.page_link_viewed', $link, ['version' => $link->version]);
        $url = PageUrl::of($link);

        return ['link' => [
            'state' => ['value' => $link->status()->value, 'label' => $link->status()->label()],
            'url' => $url,
            'needsRotation' => $url === null,
            'version' => $link->version,
            'createdOn' => Days::moment($link->created_at),
            'rotatedOn' => $link->rotated_at === null ? null : Days::moment($link->rotated_at),
            'expiresOn' => $link->expires_at === null ? null : Days::moment($link->expires_at),
            'expiresDay' => $link->expires_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
        ]];
    }
}
