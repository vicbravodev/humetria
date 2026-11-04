<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/**
 * The ticket the public form carries: the signed proof that the form was opened a moment ago, for this channel. It stands
 * where the session's forgery token would, and costs nothing to keep because nothing is kept: it holds the second it was
 * issued and a signature with the application key over it and the channel's code. A bot that submits at once, or a ticket
 * too old, is refused; what stops a bot that waits is the limit per address.
 */
final readonly class TicketSigner
{
    public function issue(string $code): string
    {
        $issuedAt = now()->getTimestamp();

        return $issuedAt.'.'.$this->signature($code, $issuedAt);
    }

    public function isValid(string $ticket, string $code): bool
    {
        [$issuedAt, $signature] = array_pad(explode('.', $ticket, 2), 2, '');

        if (! ctype_digit($issuedAt) || ! hash_equals($this->signature($code, (int) $issuedAt), $signature)) {
            return false;
        }

        $age = now()->getTimestamp() - (int) $issuedAt;

        return $age >= config()->integer('complaints.ticket.min_seconds') && $age <= config()->integer('complaints.ticket.max_seconds');
    }

    private function signature(string $code, int $issuedAt): string
    {
        return hash_hmac('sha256', strtoupper($code).'|'.$issuedAt, config()->string('app.key'));
    }
}
