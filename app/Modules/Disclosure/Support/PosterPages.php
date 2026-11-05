<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * What the printable poster receives (criterio C-55): the company, the address of the page in large text and its QR. The address
 * is a credential: seeing the poster goes to the audit trail, without it. With no link, or one that can no longer be derived (its
 * app key was dropped), there is no poster to print.
 */
final readonly class PosterPages
{
    public function __construct(private Companies $companies, private QrCode $qr, private RecordAudit $audit, private ComplaintChannelFeed $channel) {}

    /**
     * The poster of the company (its QR leads to the list of its centers) or, with a center, the letter sheet of that center,
     * whose QR leads straight to its page (board V4-Carteles). It promises a complaint can be made there only when the channel
     * takes one.
     *
     * @return array{company: string, center: array{id: string, name: string}|null, url: string, qr: string, active: bool, channel: bool, today: string}
     *
     * @throws ModelNotFoundException when there is no link to print.
     */
    public function show(?WorkCenterSummary $center = null): array
    {
        $link = PageLink::query()->first() ?? throw new ModelNotFoundException;
        $token = $link->token() ?? throw new ModelNotFoundException;
        $url = $center === null ? route('workers.show', $token) : route('workers.center', [$token, $center->id]);
        $this->audit->handle('disclosure.poster_viewed', $link, ['version' => $link->version, ...($center === null ? [] : ['work_center_id' => $center->id])]);

        return [
            'company' => $this->companies->current()->tradeName,
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'url' => $url,
            'qr' => $this->qr->dataUri($url),
            'active' => $link->status() === PageLinkStatus::Active,
            'channel' => $this->channel->current() !== null,
            'today' => Days::today(),
        ];
    }
}
