<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Messaging\Data\CompanyRejections;
use App\Modules\Messaging\Data\OptOutTally;
use App\Modules\Messaging\Data\TemplateTraffic;
use App\Modules\Messaging\Data\WhatsAppConnection;
use Carbon\CarbonImmutable;

/**
 * The messaging of the whole platform, for its console: counts and states only, never a message, a phone or a person. A message
 * counts in the range it was created in, with the status it has now; «rejected» is the spec's «sin entregar» (failed, bounced,
 * complained and paused). A company is named only from five rejections up (rule 10).
 */
interface PlatformDeliveries
{
    /**
     * The messages created in [from, to), by channel. «Today's rejections» is the sum of `rejected` over the UTC range of the day.
     *
     * @return list<ChannelTraffic> mail first, then WhatsApp; a channel with no messages is not there.
     */
    public function byChannel(CarbonImmutable $from, CarbonImmutable $to): array;

    /** @return list<TemplateTraffic> every WhatsApp template in code, in its order, a template that sent nothing at zero. */
    public function byTemplate(CarbonImmutable $from, CarbonImmutable $to): array;

    /** @return list<CompanyRejections> the companies with five or more rejections in [from, to), most first, up to the limit. */
    public function mostRejected(CarbonImmutable $from, CarbonImmutable $to, int $limit): array;

    /** When the provider (meta, resend) last reached the webhook; null when it never did or the retention removed it. */
    public function lastWebhookAt(string $provider): ?CarbonImmutable;

    public function connection(): WhatsAppConnection;

    public function optOuts(): OptOutTally;
}
