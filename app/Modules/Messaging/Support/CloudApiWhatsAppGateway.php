<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends the template through Meta's Cloud API. The message id travels in `biz_opaque_callback_data`, which Meta hands back in
 * the status webhooks, because a message's state cannot be asked for later.
 *
 * Supuesto por confirmar (Victor/Fase 2): `biz_opaque_callback_data` and the button payload follow Meta's documentation of
 * the Cloud API; the pages checked on 2026-11-18 did not restate the former.
 */
final class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(private readonly PhoneForMeta $phones, private readonly WhatsAppErrorMap $errors) {}

    public function send(OutboundMessage $message): SendOutcome
    {
        $to = $this->phones->toMeta($message->to);

        // Not a Mexican mobile: there is no WhatsApp account to reach, and nothing leaves.
        if ($to === null) {
            return SendOutcome::failed(FailureCause::NoWhatsappAccount);
        }

        $url = sprintf('%s/%s/%s/messages', config()->string('whatsapp.base_url'), config()->string('whatsapp.api_version'), config()->string('whatsapp.phone_number_id'));

        try {
            $response = Http::timeout(config()->integer('whatsapp.timeout_seconds'))
                ->withToken(config()->string('whatsapp.access_token'))
                ->acceptJson()
                ->asJson()
                ->post($url, $this->payload($message, $to));
        } catch (ConnectionException) {
            // It is not known whether the message left.
            return SendOutcome::failed(FailureCause::TimedOut);
        }

        if ($response->successful()) {
            $id = $response->json('messages.0.id');

            return is_string($id) && $id !== '' ? SendOutcome::accepted($id) : SendOutcome::failed(FailureCause::ServerError);
        }

        $code = $response->json('error.code');

        return SendOutcome::failed($this->errors->fromResponse($response->status(), is_int($code) ? $code : null));
    }

    /** @return array<string, mixed> */
    private function payload(OutboundMessage $message, string $to): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'biz_opaque_callback_data' => $message->callbackData,
            'template' => [
                'name' => $message->template,
                'language' => ['code' => $message->language],
                'components' => [
                    ['type' => 'body', 'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $message->bodyParameters)],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $message->buttonSuffix]]],
                ],
            ],
        ];
    }
}
