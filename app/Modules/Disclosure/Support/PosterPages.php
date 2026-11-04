<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * What the printable poster receives (criterio C-55): the company, the address of the page in large text and its QR. The address
 * is a credential: seeing the poster goes to the audit trail, without it. With no link, or one that can no longer be derived (its
 * app key was dropped), there is no poster to print.
 */
final readonly class PosterPages
{
    public function __construct(private Companies $companies, private QrCode $qr, private RecordAudit $audit) {}

    /**
     * @return array{company: string, url: string, qr: string, active: bool}
     *
     * @throws ModelNotFoundException when there is no link to print.
     */
    public function show(): array
    {
        $link = PageLink::query()->first() ?? throw new ModelNotFoundException;
        $url = PageUrl::of($link) ?? throw new ModelNotFoundException;
        $this->audit->handle('disclosure.poster_viewed', $link, ['version' => $link->version]);

        return ['company' => $this->companies->current()->tradeName, 'url' => $url, 'qr' => $this->qr->dataUri($url), 'active' => $link->status() === PageLinkStatus::Active];
    }
}
