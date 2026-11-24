<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Actions\HandleResendEvents;
use App\Modules\Messaging\Listeners\StampMailDelivery;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends by the HTTP API of Resend (no package). Over SMTP Resend replaces the Message-ID and drops our headers, so nothing of
 * ours comes back in its webhooks; over the API a tag does. The id on StampMailDelivery::HEADER travels as the `delivery_id`
 * tag, and the header itself is not sent.
 */
final class ResendApiTransport extends AbstractTransport
{
    private const string ENDPOINT = 'https://api.resend.com/emails';

    private const array OWN_FIELDS = ['from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'content-type', 'mime-version', 'date', 'message-id'];

    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'resend+api';
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException('Resend sends only a composed message.');
        }

        $email = MessageConverter::toEmail($original);

        $response = Http::withToken($this->key)->acceptJson()->timeout(15)->post(self::ENDPOINT, $this->payload($email, $message->getEnvelope()));

        if ($response->failed()) {
            // The status only: the body of an error may echo the address.
            throw new TransportException('Resend refused the mail (HTTP '.$response->status().').', $response->status());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        $headers = [];

        foreach ($email->getHeaders()->getNames() as $name) {
            if (is_string($name) && ! in_array(strtolower($name), self::OWN_FIELDS, true) && $name !== StampMailDelivery::HEADER) {
                $headers[$name] = $email->getHeaders()->get($name)?->getBodyAsString();
            }
        }

        $recipients = array_map(fn (Address $address): string => $address->getAddress(), $envelope->getRecipients());
        $to = $this->addresses($email->getTo());
        $from = $this->addresses($email->getFrom());
        $hidden = array_values(array_diff($recipients, array_map(fn (Address $address): string => $address->getAddress(), [...$email->getTo(), ...$email->getCc()])));

        $payload = array_filter([
            // The From of the message, with its name: the envelope sender is the return path, which has none.
            'from' => $from === [] ? $envelope->getSender()->toString() : implode(', ', $from),
            'to' => $to,
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $hidden,
            'reply_to' => $this->addresses($email->getReplyTo()),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'headers' => $headers,
            'attachments' => array_map(fn ($part): array => [
                'filename' => $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'),
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments()),
        ], fn (mixed $value): bool => $value !== [] && $value !== null);

        $deliveryId = $email->getHeaders()->get(StampMailDelivery::HEADER)?->getBodyAsString();

        if ($deliveryId !== null) {
            $payload['tags'] = [['name' => HandleResendEvents::TAG, 'value' => $deliveryId]];
        }

        return $payload;
    }

    /**
     * @param  array<Address>  $list
     * @return list<string>
     */
    private function addresses(array $list): array
    {
        return array_values(array_map(fn (Address $address): string => $address->toString(), $list));
    }
}
