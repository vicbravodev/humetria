<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Support\Str;

/** The poster the company prints and hangs: its name, the QR and the address of the channel, and a paragraph of its procedure. */
final readonly class PosterPages
{
    private const int PROCEDURE_CHARACTERS = 400;

    public function __construct(private Companies $companies, private QrCodeImage $qr) {}

    /**
     * @return array<string, mixed>|null null when the company has no channel that was ever activated.
     */
    public function show(): ?array
    {
        $channel = ComplaintChannel::query()->first();

        if ($channel === null || $channel->enabled_at === null) {
            return null;
        }

        $url = $channel->publicUrl();

        return [
            'company' => $this->companies->current()->tradeName,
            'url' => $url,
            'shortUrl' => (string) preg_replace('#^https?://#', '', $url),
            'qr' => $this->qr->dataUri($url),
            'procedure' => Str::limit(trim($channel->procedure), self::PROCEDURE_CHARACTERS, '…', true),
            'paused' => $channel->status() === ChannelStatus::Paused,
        ];
    }
}
