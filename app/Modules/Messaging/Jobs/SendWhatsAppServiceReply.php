<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Jobs;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Exceptions\RetryableWhatsAppFailure;
use App\Modules\Messaging\Support\WhatsAppSwitch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * The answer to a STOP or an ALTA: one free text, inside the 24 hours that the person's own message opened. It is no delivery of
 * any company: it is not metered and nobody is charged. It carries the phone, so the payload is encrypted; the phone and the text
 * never reach a log. The emergency pause and WHATSAPP_ENABLED stop it like any other send.
 */
final class SendWhatsAppServiceReply implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @param 'opted_out'|'opted_in' $kind the key of `whatsapp.replies` that holds the text */
    public function __construct(public readonly string $to, public readonly string $kind)
    {
        $this->onQueue('whatsapp');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(WhatsAppGateway $gateway, WhatsAppSwitch $switch): void
    {
        if (! $switch->available()) {
            return;
        }

        $outcome = $gateway->sendText($this->to, config()->string("whatsapp.replies.{$this->kind}"));

        if ($outcome->isAccepted() || $outcome->cause === null) {
            return;
        }

        if ($outcome->cause->retryable()) {
            throw new RetryableWhatsAppFailure($outcome->cause->value);
        }

        Log::warning('whatsapp.service_reply_failed', ['kind' => $this->kind, 'cause' => $outcome->cause->value]);
    }
}
