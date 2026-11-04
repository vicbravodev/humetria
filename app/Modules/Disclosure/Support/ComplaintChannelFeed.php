<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Complaints\Contracts\ComplaintChannels;

/**
 * The channel of the company for complaints and reports as the public page repeats it (num. 5.7 d; criterio C-52), read through
 * the channel's contract and copied field by field: only the link (and only when it stays in this app), whether anonymous ones
 * are accepted and what it takes, in words of its own for the types it knows. Its procedure, deadlines, handlers and cases never
 * cross: nothing of a case, not even that there is one. It is null while the channel takes nothing.
 */
final readonly class ComplaintChannelFeed
{
    private const array CATEGORIES = [
        'unfavorable_practice' => 'Quejas por prácticas opuestas a un entorno favorable',
        'workplace_violence' => 'Denuncias de violencia laboral',
        'traumatic_event_notice' => 'Avisos de acontecimientos traumáticos severos',
    ];

    public function __construct(private ComplaintChannels $channels) {}

    public function current(): ?PublicChannel
    {
        $channel = $this->channels->current();

        if (! $channel->available || $channel->url === null || parse_url($channel->url, PHP_URL_HOST) !== parse_url(url('/'), PHP_URL_HOST)) {
            return null;
        }

        return new PublicChannel($channel->url, $channel->anonymousAllowed, array_values(array_filter(array_map(fn (string $type): ?string => self::CATEGORIES[$type] ?? null, $channel->types))));
    }
}
